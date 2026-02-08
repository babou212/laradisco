<?php

namespace App\Models;

use App\Concerns\ClearsCaches;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class DirectMessage extends Model
{
    /** @use HasFactory<\Database\Factories\DirectMessageFactory> */
    use ClearsCaches, HasFactory, Searchable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'direct_message_group_id',
        'user_id',
        'content',
        'is_edited',
        'edited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * Clear caches related to this message.
     */
    public function clearCaches(): void
    {
        // Clear DM groups cache for all participants
        if ($this->group) {
            foreach ($this->group->participants as $participant) {
                cache()->forget("user.{$participant->id}.dm_groups");
            }
        }
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        // Eager load group and participants to avoid N+1 during indexing
        $this->loadMissing('group.participants');

        return [
            'id' => $this->id,
            'content' => $this->content,
            'direct_message_group_id' => $this->direct_message_group_id,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at->timestamp,
            // Flatten participant IDs for array filtering
            'participant_ids' => $this->group?->participants->unique()->pluck('id')->values()->toArray() ?? [],
        ];
    }
}
