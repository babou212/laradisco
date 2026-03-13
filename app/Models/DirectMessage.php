<?php

namespace App\Models;

use App\Concerns\ClearsCaches;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectMessage extends Model
{
    /** @use HasFactory<\Database\Factories\DirectMessageFactory> */
    use ClearsCaches, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'direct_message_group_id',
        'user_id',
        'content',
        'is_encrypted',
        'sender_device_id',
        'is_edited',
        'edited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
            'is_edited' => 'boolean',
            'edited_at' => 'datetime',
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
     * @return HasMany<DirectMessageReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(DirectMessageReaction::class);
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
