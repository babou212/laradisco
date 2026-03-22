<?php

namespace App\Models;

use Database\Factories\ThreadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $last_message_at
 */
class Thread extends Model
{
    /** @use HasFactory<ThreadFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'channel_id',
        'user_id',
        'message_id',
        'name',
        'is_archived',
        'is_locked',
        'message_count',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'is_locked' => 'boolean',
            'last_message_at' => 'datetime',
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
     * The parent message that started this thread.
     *
     * @return BelongsTo<Message, $this>
     */
    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The most recent reply in this thread.
     *
     * @return HasOne<Message, $this>
     */
    public function latestReply(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Users following this thread for notifications.
     *
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'thread_followers')->withTimestamps();
    }
}
