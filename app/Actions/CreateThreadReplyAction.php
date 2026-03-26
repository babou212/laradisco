<?php

namespace App\Actions;

use App\Enums\PermissionFlag;
use App\Events\ThreadMessageSent;
use App\Events\ThreadUpdated;
use App\Http\Resources\MessageResource;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Services\MentionService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CreateThreadReplyAction
{
    public function __construct(
        private readonly MentionService $mentionService,
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * @param  array{sender_device_id: string|null, history_ciphertext: string|null, message_bytes: string|null, epoch: int, thread_name: string|null, mention_user_ids: array<int>, mention_everyone: bool, mention_here: bool}  $data
     */
    public function execute(User $user, Channel $channel, Message $parentMessage, array $data): CreateThreadReplyResult
    {
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

        $result = DB::transaction(function () use ($data, $channel, $user, $thread) {
            $reply = $channel->messages()->create([
                'user_id' => $user->id,
                'thread_id' => $thread->id,
                'sender_device_id' => $data['sender_device_id'] ?? null,
                'history_ciphertext' => $data['history_ciphertext'] ?? null,
                'message_bytes' => $data['message_bytes'] ?? null,
                'epoch' => $data['epoch'],
            ]);

            $thread->increment('message_count');
            $thread->update(['last_message_at' => now()]);
            $thread->followers()->syncWithoutDetaching([$user->id]);

            return $reply;
        });

        $result->load(['user:id,username,name,nickname,avatar_path,status,custom_status', 'attachments']);

        $this->mentionService->processMentionsFromMetadata(
            $result,
            $data['mention_user_ids'],
            $data['mention_everyone'],
            $data['mention_here'],
        );

        broadcast(new ThreadMessageSent($result))->toOthers();

        $thread->refresh();
        broadcast(new ThreadUpdated($thread))->toOthers();

        return CreateThreadReplyResult::success($result);
    }
}
