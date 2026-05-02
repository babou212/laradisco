<?php

namespace App\Models;

use App\Concerns\ClearsCaches;
use App\Support\CacheKeys;
use Database\Factories\DirectMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property Carbon|null $edited_at
 */
class DirectMessage extends Model
{
    /** @use HasFactory<DirectMessageFactory> */
    use ClearsCaches, HasFactory, Searchable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'direct_message_group_id',
        'user_id',
        'reply_to_id',
        'client_temp_id',
        'content',
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
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function searchableAs(): string
    {
        return 'direct_messages';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing('user');

        return [
            'id' => (string) $this->id,
            'content' => (string) $this->content,
            'user_id' => (int) $this->user_id,
            'user_username' => (string) ($this->user?->username ?? ''),
            'direct_message_group_id' => (int) $this->direct_message_group_id,
            'created_at_ts' => $this->created_at?->timestamp ?? 0,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->deleted_at === null && trim((string) $this->content) !== '';
    }

    /**
     * Clear caches related to this message.
     */
    public function clearCaches(): void
    {
        $this->loadMissing('group.participants');

        if ($this->group) {
            foreach ($this->group->participants as $participant) {
                cache()->forget("user.{$participant->id}.dm_groups");
            }
        }
    }

    /**
     * @return list<string>
     */
    public function cacheTags(): array
    {
        if ($this->direct_message_group_id) {
            return [CacheKeys::dmGroupTag($this->direct_message_group_id)];
        }

        return [];
    }
}
