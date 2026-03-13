<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EncryptedSearchToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'conversation_type',
        'conversation_id',
        'token',
        'message_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Scope: find tokens matching a set of trapdoors in a conversation.
     */
    public function scopeForConversation($query, string $type, int $id)
    {
        return $query->where('conversation_type', $type)
            ->where('conversation_id', $id);
    }

    /**
     * Scope: match against one or more trapdoor tokens.
     */
    public function scopeMatchingTokens($query, array $tokens)
    {
        return $query->whereIn('token', $tokens);
    }

    /**
     * Bulk insert search tokens for a message.
     */
    public static function insertTokensForMessage(
        string $conversationType,
        int $conversationId,
        int $messageId,
        array $tokens,
    ): void {
        if (empty($tokens)) {
            return;
        }

        $now = now();
        $records = array_map(fn (string $token) => [
            'conversation_type' => $conversationType,
            'conversation_id' => $conversationId,
            'token' => $token,
            'message_id' => $messageId,
            'created_at' => $now,
        ], $tokens);

        static::insert($records);
    }

    /**
     * Replace all tokens for a message (used on edit).
     */
    public static function replaceTokensForMessage(
        string $conversationType,
        int $conversationId,
        int $messageId,
        array $tokens,
    ): void {
        static::where('message_id', $messageId)->delete();
        static::insertTokensForMessage($conversationType, $conversationId, $messageId, $tokens);
    }

    /**
     * Delete all tokens for a message.
     */
    public static function deleteTokensForMessage(int $messageId): void
    {
        static::where('message_id', $messageId)->delete();
    }
}
