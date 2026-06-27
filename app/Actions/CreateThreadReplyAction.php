<?php

namespace App\Actions;

use App\Enums\PermissionFlag;
use App\Events\ThreadMessageSent;
use App\Events\ThreadUpdated;
use App\Models\Channel;
use App\Models\Mention;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\ThreadReplyNotification;
use App\Services\MentionService;
use App\Services\PermissionService;
use App\Support\CacheKeys;
use App\Support\Media\AttachmentRebinder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CreateThreadReplyAction
{
    public function __construct(
        private readonly MentionService $mentionService,
        private readonly PermissionService $permissionService,
        private readonly AttachmentRebinder $attachmentRebinder,
    ) {}

    /**
     * @param  array{content: string|null, attachment_ids?: array<string>, thread_name: string|null, mention_user_ids: array<int>, mention_everyone: bool, mention_here: bool, client_temp_id?: string|null}  $data
     */
    public function execute(User $user, Channel $channel, Message $parentMessage, array $data): CreateThreadReplyResult
    {
        $clientTempId = $data['client_temp_id'] ?? null;
        if ($clientTempId) {
            $existing = $channel->messages()
                ->where('user_id', $user->id)
                ->where('client_temp_id', $clientTempId)
                ->whereNotNull('thread_id')
                ->first();
            if ($existing) {
                $existing->load(['user:id,username,status,custom_status', 'attachments']);

                return CreateThreadReplyResult::duplicate($existing);
            }
        }

        $thread = $parentMessage->threadStarted;

        if (! $thread) {
            if (! $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::CreateThreads)) {
                return CreateThreadReplyResult::forbidden('You do not have permission to create threads in this channel.');
            }

            $thread = Thread::create([
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'message_id' => $parentMessage->id,
                'name' => $data['thread_name'] ?? 'Thread',
                'message_count' => 0,
                'last_message_at' => now(),
            ]);

            $followerIds = array_unique([$user->id, $parentMessage->user_id]);
            $thread->followers()->attach($followerIds);
        } else {
            if (! $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::SendThreadMessages)) {
                return CreateThreadReplyResult::forbidden('You do not have permission to send messages in threads.');
            }
        }

        if ($thread->is_locked) {
            return CreateThreadReplyResult::forbidden('This thread is locked.');
        }

        $result = DB::transaction(function () use ($data, $channel, $user, $thread, $clientTempId) {
            $reply = $channel->messages()->create([
                'user_id' => $user->id,
                'thread_id' => $thread->id,
                'client_temp_id' => $clientTempId,
                'content' => $data['content'] ?? null,
            ]);

            $this->attachmentRebinder->rebind($user, $reply, $data['attachment_ids'] ?? []);

            $thread->increment('message_count');
            $thread->update(['last_message_at' => now()]);
            $thread->followers()->syncWithoutDetaching([$user->id]);

            return $reply;
        });

        $result->load(['user:id,username,status,custom_status', 'attachments']);

        $mentions = $this->mentionService->processMentionsFromMetadata(
            $result,
            $data['mention_user_ids'],
            $data['mention_everyone'],
            $data['mention_here'],
        );

        $this->notifyFollowers($thread, $user, $result, $mentions);

        broadcast(new ThreadMessageSent($result))->toOthers();

        $thread->refresh();
        broadcast(new ThreadUpdated($thread))->toOthers();

        Cache::tags([CacheKeys::threadMessagesTag($thread->id)])->flush();
        Cache::tags([CacheKeys::channelMessagesTag($channel->id)])->flush();

        return CreateThreadReplyResult::success($result);
    }

    /**
     * Notify everyone following the thread that a new reply was posted.
     *
     * Excludes the poster and anyone already notified by a mention in the same
     * reply (mention wins, so they aren't double-notified). When the reply mentions
     * everyone/here, every follower already received a mention notification, so the
     * thread-reply fan-out is skipped entirely.
     *
     * @param  Collection<int, Mention>  $mentions
     */
    private function notifyFollowers(Thread $thread, User $user, Message $reply, Collection $mentions): void
    {
        if ($mentions->contains(fn (Mention $mention) => in_array($mention->type, ['everyone', 'here'], true))) {
            return;
        }

        $excludedIds = $mentions->pluck('user_id')
            ->filter()
            ->push($user->id)
            ->unique()
            ->all();

        $recipients = $thread->followers()
            ->whereNotIn('users.id', $excludedIds)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ThreadReplyNotification($reply));
        }
    }
}
