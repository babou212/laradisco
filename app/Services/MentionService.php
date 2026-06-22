<?php

namespace App\Services;

use App\Enums\PermissionFlag;
use App\Enums\UserStatusType;
use App\Events\MessageSent;
use App\Models\Mention;
use App\Models\Message;
use App\Models\User;
use App\Notifications\MentionNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class MentionService
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly InboxService $inboxService,
    ) {}

    /**
     * Notify all users in the server (@everyone).
     */
    protected function notifyEveryone(Message $message): void
    {
        User::query()
            ->without('media')
            ->where('id', '!=', $message->user_id)
            ->select(['id', 'email', 'username'])
            ->cursor()
            ->chunk(100)
            ->each(function ($chunk) use ($message) {
                Notification::send(
                    $chunk->collect(),
                    new MentionNotification($message, 'everyone')
                );
            });
    }

    /**
     * Notify all non-offline users (@here).
     * Uses cursor to avoid loading all users into memory.
     */
    protected function notifyHere(Message $message): void
    {
        User::query()
            ->without('media')
            ->where('status', '!=', UserStatusType::Offline)
            ->where('id', '!=', $message->user_id)
            ->select(['id', 'email', 'username'])
            ->cursor()
            ->chunk(100)
            ->each(function ($chunk) use ($message) {
                Notification::send(
                    $chunk->collect(),
                    new MentionNotification($message, 'here')
                );
            });
    }

    /**
     * Process mentions from client-provided metadata (for encrypted messages).
     * The client extracts mention targets from plaintext before encryption
     * and passes them as separate unencrypted metadata.
     *
     * @param  array<int>  $mentionUserIds
     * @return Collection<int, Mention>
     */
    public function processMentionsFromMetadata(
        Message $message,
        array $mentionUserIds = [],
        bool $mentionEveryone = false,
        bool $mentionHere = false,
    ): Collection {
        $mentions = collect();

        if ($mentionEveryone) {
            if ($this->permissionService->userCanInChannel($message->user, $message->channel, PermissionFlag::MentionEveryone)) {
                $mention = $message->mentions()->create([
                    'user_id' => null,
                    'type' => 'everyone',
                ]);
                $mentions->push($mention);
                $this->notifyEveryone($message);
            }

            return $mentions;
        }

        if ($mentionHere) {
            if ($this->permissionService->userCanInChannel($message->user, $message->channel, PermissionFlag::MentionEveryone)) {
                $mention = $message->mentions()->create([
                    'user_id' => null,
                    'type' => 'here',
                ]);
                $mentions->push($mention);
                $this->notifyHere($message);
            }

            return $mentions;
        }

        if (! empty($mentionUserIds)) {
            $userIds = array_filter($mentionUserIds, fn (int $id) => $id !== $message->user_id);

            $mentionedUsers = User::query()
                ->whereIn('id', $userIds)
                ->get();

            foreach ($mentionedUsers as $user) {
                $mention = $message->mentions()->create([
                    'user_id' => $user->id,
                    'type' => 'user',
                ]);
                $mentions->push($mention);
            }

            if ($mentionedUsers->isNotEmpty()) {
                Notification::send(
                    $mentionedUsers,
                    new MentionNotification($message, 'user')
                );

                // Inbox delivery for offline @mentioned users. @everyone/@here
                // are broadcast-scale and intentionally not enqueued.
                $this->inboxService->enqueueForRecipients(
                    $mentionedUsers->pluck('id'),
                    'channel',
                    $message->id,
                    (new MessageSent($message))->broadcastWith()['message'],
                );
            }
        }

        return $mentions;
    }
}
