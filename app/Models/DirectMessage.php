<?php

namespace App\Models;

use App\Concerns\ClearsCaches;
use Database\Factories\DirectMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $edited_at
 */
class DirectMessage extends Model
{
    /** @use HasFactory<DirectMessageFactory> */
    use ClearsCaches, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'direct_message_group_id',
        'user_id',
        'reply_to_id',
        'sender_device_id',
        'history_ciphertext',
        'message_bytes',
        'epoch',
        'is_pinned',
        'is_edited',
        'edited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_edited' => 'boolean',
            'edited_at' => 'datetime',
            'epoch' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DirectMessageGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(DirectMessageGroup::class, 'direct_message_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * @return HasMany<DirectMessageReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(DirectMessageReaction::class);
    }

    /**
     * @return MorphMany<EncryptedAttachment, $this>
     */
    public function encryptedAttachments(): MorphMany
    {
        return $this->morphMany(EncryptedAttachment::class, 'attachable');
    }

    /**
     * Clear caches related to this message.
     */
    public function clearCaches(): void
    {
        // Clear DM groups cache for all participants
        $this->loadMissing('group.participants');

        if ($this->group) {
            foreach ($this->group->participants as $participant) {
                cache()->forget("user.{$participant->id}.dm_groups");
            }
        }
    }
}
