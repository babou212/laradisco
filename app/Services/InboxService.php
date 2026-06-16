<?php

namespace App\Services;

use App\Models\InboxMessage;
use App\Models\User;
use Illuminate\Support\Collection;

class InboxService
{
    /**
     * Safety cap on a single drain. The client re-pulls if it hits the cap.
     */
    public const PENDING_LIMIT = 500;

    /**
     * Write one inbox row per recipient. Idempotent via upsert on the
     * (user_id, message_type, message_id) unique key — re-enqueues are no-ops
     * and an existing payload is never overwritten.
     *
     * @param  iterable<int>  $recipientIds  recipient user ids (sender already excluded)
     * @param  'channel'|'direct_message'  $messageType
     * @param  array<string, mixed>  $payload  the broadcast `message` object
     */
    public function enqueueForRecipients(
        iterable $recipientIds,
        string $messageType,
        int $messageId,
        array $payload,
    ): void {
        $now = now();
        $encoded = json_encode($payload);

        $rows = [];
        foreach ($recipientIds as $recipientId) {
            $rows[] = [
                'user_id' => $recipientId,
                'message_type' => $messageType,
                'message_id' => $messageId,
                'payload' => $encoded,
                'created_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        // ON CONFLICT DO NOTHING via the (user_id, message_type, message_id)
        // unique key — a re-enqueue never overwrites an existing payload.
        InboxMessage::query()->insertOrIgnore($rows);
    }

    /**
     * Pending inbox rows for a user, oldest first.
     *
     * @return Collection<int, InboxMessage>
     */
    public function pendingForUser(User $user): Collection
    {
        return InboxMessage::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->limit(self::PENDING_LIMIT)
            ->get();
    }

    /**
     * Idempotently delete acked rows for a user. Missing rows are a no-op.
     *
     * @param  array<int, array{message_type: string, message_id: int}>  $items
     */
    public function ack(User $user, array $items): int
    {
        if ($items === []) {
            return 0;
        }

        return InboxMessage::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($items) {
                foreach ($items as $item) {
                    $query->orWhere(fn ($w) => $w
                        ->where('message_type', $item['message_type'])
                        ->where('message_id', $item['message_id']));
                }
            })
            ->delete();
    }
}
