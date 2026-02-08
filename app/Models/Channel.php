<?php

namespace App\Models;

use App\Enums\ChannelType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Channel extends Model
{
    /** @use HasFactory<\Database\Factories\ChannelFactory> */
    use HasFactory, Searchable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'topic',
        'type',
        'position',
        'is_private',
        'slowmode_seconds',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'is_private' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'topic' => $this->topic,
            'type' => $this->type,
            'is_private' => $this->is_private,
        ];
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasMany<Thread, $this>
     */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }

    /**
     * @return HasMany<ChannelPermissionOverride, $this>
     */
    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(ChannelPermissionOverride::class);
    }

    /**
     * Get pinned messages in this channel.
     *
     * @return HasMany<Message, $this>
     */
    public function pinnedMessages(): HasMany
    {
        return $this->hasMany(Message::class)->where('is_pinned', true);
    }
}
