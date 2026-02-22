<?php

namespace App\Services;

use App\Enums\PermissionFlag;
use App\Enums\UserStatusType;
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
    ) {}

    /**
     * Parse mentions from message content and create mention records + notifications.
     *
     * @return Collection<int, Mention>
     */
    public function processMentions(Message $message): Collection
    {
        $content = $message->content;
        $mentions = collect();

        if (preg_match('/@everyone\b/', $content)) {
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

        if (preg_match('/@here\b/', $content)) {
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

        preg_match_all('/@(\w+)/', $content, $matches);
        $usernames = array_unique($matches[1] ?? []);

        if (empty($usernames)) {
            return $mentions;
        }

        $mentionedUsers = User::query()
            ->whereIn('username', $usernames)
            ->where('id', '!=', $message->user_id)
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
        }

        return $mentions;
    }

    /**
     * Notify all users in the server (@everyone).
     */
    protected function notifyEveryone(Message $message): void
    {
        $users = User::query()
            ->where('id', '!=', $message->user_id)
            ->get();

        Notification::send(
            $users,
            new MentionNotification($message, 'everyone')
        );
    }

    /**
     * Notify all non-offline users (@here).
     */
    protected function notifyHere(Message $message): void
    {
        $users = User::query()
            ->where('status', '!=', UserStatusType::Offline)
            ->where('id', '!=', $message->user_id)
            ->get();

        Notification::send(
            $users,
            new MentionNotification($message, 'here')
        );
    }

    /**
     * Extract mention data from content for frontend rendering.
     *
     * @return array{usernames: list<string>, hasEveryone: bool, hasHere: bool}
     */
    public static function extractMentions(string $content): array
    {
        $hasEveryone = (bool) preg_match('/@everyone\b/', $content);
        $hasHere = (bool) preg_match('/@here\b/', $content);

        preg_match_all('/@(\w+)/', $content, $matches);
        $usernames = array_values(array_unique(
            array_filter($matches[1] ?? [], fn (string $u) => $u !== 'everyone' && $u !== 'here')
        ));

        return [
            'usernames' => $usernames,
            'hasEveryone' => $hasEveryone,
            'hasHere' => $hasHere,
        ];
    }
}
