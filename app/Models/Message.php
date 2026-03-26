<?php

namespace App\Models;

use App\Concerns\ClearsCaches;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $edited_at
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use ClearsCaches, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'channel_id',
        'user_id',
        'thread_id',
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
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Thread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * @return HasMany<MessageAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /**
     * @return HasMany<MessageReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * @return HasMany<Mention, $this>
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }

    /**
     * The thread started from this message (if any).
     *
     * @return HasOne<Thread, $this>
     */
    public function threadStarted(): HasOne
    {
        return $this->hasOne(Thread::class, 'message_id');
    }

    /**
     * Clear caches related to this message.
     */
    public function clearCaches(): void
    {
        // Clear channel metadata cache
        if ($this->channel_id) {
            cache()->forget("channel.{$this->channel_id}.metadata");
        }
    }
}
