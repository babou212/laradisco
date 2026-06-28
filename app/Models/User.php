<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PermissionFlag;
use App\Support\CacheKeys;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, InteractsWithMedia, Notifiable, TwoFactorAuthenticatable {
        HasRoles::hasPermissionTo as protected spatieHasPermissionTo;
        HasRoles::roles as protected spatieRoles;
    }

    /**
     * Narrow Spatie HasRoles' generic-less return type so static analysis
     * sees `Role` (not `Model`) when iterating `$user->roles`.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->spatieRoles();
    }

    /**
     * Relationships eager loaded on every query.
     *
     * Avatars are exposed via the `avatar_urls` accessor (read from Spatie's
     * `media` relation)
     *
     * @var list<string>
     */
    protected $with = ['media'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'about_me',
        'custom_status',
        'show_activity',
        'must_setup',
        'status',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'must_setup',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_setup' => 'boolean',
            'show_activity' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'last_seen_at' => 'datetime',
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
     * @return HasMany<DirectMessage, $this>
     */
    public function directMessages(): HasMany
    {
        return $this->hasMany(DirectMessage::class);
    }

    /**
     * @return BelongsToMany<DirectMessageGroup, $this>
     */
    public function directMessageGroups(): BelongsToMany
    {
        return $this->belongsToMany(DirectMessageGroup::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Channel, $this>
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<MessageReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * Override Spatie's hasPermissionTo to gracefully handle missing permissions.
     *
     * When the permissions table hasn't been seeded yet, Spatie throws
     * PermissionDoesNotExist. We catch it and return false instead of crashing.
     */
    public function hasPermissionTo(string|Permission $permission, ?string $guardName = null): bool
    {
        try {
            return $this->spatieHasPermissionTo($permission, $guardName);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * Check if the user has a specific permission flag (via Spatie).
     */
    public function hasPermissionFlag(PermissionFlag $permission): bool
    {
        if ($this->hasPermissionTo('administrator')) {
            return true;
        }

        return $this->hasPermissionTo($permission->value);
    }

    /**
     * Check if the user has the Administrator permission.
     */
    public function isAdministrator(): bool
    {
        return $this->hasPermissionTo('administrator');
    }

    /**
     * Check if the user is currently banned.
     *
     * Runs on every authenticated request (CheckBanned middleware) and in the
     * current-user permissions payload. Only the negative (not-banned) result
     * is cached: it removes the per-request query for the common case, while a
     * banned user — and any temporary ban that expires mid-TTL — is always
     * re-evaluated against the database, so there is no stale lock-out. The
     * cache is flushed on ban/unban via the user tag in ModerationService.
     */
    public function isBanned(): bool
    {
        $tags = [CacheKeys::userTag($this->id)];
        $key = CacheKeys::userBanStatus($this->id);

        if (Cache::tags($tags)->get($key) === false) {
            return false;
        }

        $banned = $this->bans()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        if (! $banned) {
            Cache::tags($tags)->put($key, false, CacheKeys::TTL_HOT);
        }

        return $banned;
    }

    /**
     * Check if the user is jailed (has the Jailed role).
     */
    public function isJailed(): bool
    {
        return $this->hasRole('Jailed');
    }

    /**
     * Permission flags derived from the user's roles.
     *
     * @return array<string, bool>
     */
    public function permissionsPayload(): array
    {
        return [
            'canInviteMembers' => $this->isAdministrator() || $this->hasPermissionTo('invite_members'),
            'canManageRoles' => $this->isAdministrator() || $this->hasPermissionTo('manage_roles'),
            'canManageChannels' => $this->isAdministrator() || $this->hasPermissionTo('manage_channels'),
            'canManageServer' => $this->isAdministrator() || $this->hasPermissionTo('manage_server'),
            'canManageMessages' => $this->isAdministrator() || $this->hasPermissionTo('manage_messages'),
            'canBanMembers' => $this->isAdministrator() || $this->hasPermissionTo('ban_members'),
            'canKickMembers' => $this->isAdministrator() || $this->hasPermissionTo('kick_members'),
            'canViewAuditLog' => $this->isAdministrator() || $this->hasPermissionTo('view_audit_log'),
            'isAdministrator' => $this->isAdministrator(),
            'isBanned' => $this->isBanned(),
            'isJailed' => $this->isJailed(),
        ];
    }

    /**
     * @return HasMany<Ban, $this>
     */
    public function bans(): HasMany
    {
        return $this->hasMany(Ban::class);
    }

    /**
     * The user's currently active ban (timed-but-unexpired or permanent), if any.
     *
     * Queried directly rather than through the ban-status cache, which only
     * memoises the negative (not-banned) result. Used to surface the ban reason
     * and expiry to the client at login, in the CheckBanned middleware, and in
     * the UserBanned broadcast payload.
     */
    public function activeBan(): ?Ban
    {
        return $this->bans()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();
    }

    /**
     * Get the user's display name (their username).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->username;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

        $this->addMediaCollection('pending_attachments');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media?->collection_name !== 'avatar') {
            return;
        }

        $this->addMediaConversion('thumb')
            ->width(64)
            ->height(64)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('small')
            ->width(128)
            ->height(128)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('medium')
            ->width(256)
            ->height(256)
            ->nonQueued();
    }

    /**
     * Get avatar URLs from Spatie Media Library.
     *
     * @return array{thumb: string|null, small: string|null, medium: string|null, original: string}|null
     */
    public function getAvatarUrlsAttribute(): ?array
    {
        $media = $this->getFirstMedia('avatar');

        if (! $media) {
            return null;
        }

        $expiration = now()->addMinutes(15);

        return [
            'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getTemporaryUrl($expiration, 'thumb') : null,
            'small' => $media->hasGeneratedConversion('small') ? $media->getTemporaryUrl($expiration, 'small') : null,
            'medium' => $media->hasGeneratedConversion('medium') ? $media->getTemporaryUrl($expiration, 'medium') : null,
            'original' => $media->getTemporaryUrl($expiration),
        ];
    }
}
