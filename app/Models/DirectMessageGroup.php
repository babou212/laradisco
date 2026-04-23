<?php

namespace App\Models;

use Database\Factories\DirectMessageGroupFactory;
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
class DirectMessageGroup extends Model
{
    /** @use HasFactory<DirectMessageGroupFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'icon_path',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<DirectMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(DirectMessage::class);
    }

    /**
     * @return HasOne<DirectMessage, $this>
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(DirectMessage::class)->latestOfMany();
    }

    /**
     * Check if this is a one-to-one DM (exactly 2 participants).
     */
    public function isOneToOne(): bool
    {
        return $this->participants()->count() === 2;
    }
}
