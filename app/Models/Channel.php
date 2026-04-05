<?php

namespace App\Models;

use App\Concerns\ClearsCaches;
use App\Enums\ChannelType;
use App\Support\CacheKeys;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property ChannelType $type
 * @property array<string, bool>|null $channelPermissions
 */
class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use ClearsCaches, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Channel $channel): void {
            if (blank($channel->slug)) {
                $channel->slug = Str::slug($channel->name);
            }
        });
    }

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

    public function clearCaches(): void
    {
        //
    }

    /**
     * @return list<string>
     */
    public function cacheTags(): array
    {
        return [
            CacheKeys::channelTag($this->id),
            CacheKeys::TAG_SIDEBAR,
        ];
    }
}
