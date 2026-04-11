<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PermissionFlag;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, InteractsWithMedia, Notifiable, TwoFactorAuthenticatable {
        HasRoles::hasPermissionTo as protected spatieHasPermissionTo;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'nickname',
        'about_me',
        'custom_status',
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
     * @return HasMany<MessageReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * @return HasOne<UserIdentityKey, $this>
     */
    public function identityKey(): HasOne
    {
        return $this->hasOne(UserIdentityKey::class);
    }

    /**
     * @return HasMany<UserDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * @return HasMany<UserDevice, $this>
     */
    public function activeDevices(): HasMany
    {
        return $this->devices()->where('is_active', true);
    }

    /**
     * Check if the user has E2EE set up.
     */
    public function hasE2eeSetup(): bool
    {
        return $this->identityKey()->exists();
    }

    /**
     * Override Spatie's hasPermissionTo to gracefully handle missing permissions.
     *
     * When the permissions table hasn't been seeded yet, Spatie throws
     * PermissionDoesNotExist. We catch it and return false instead of crashing.
     */
    public function hasPermissionTo(string|\Spatie\Permission\Contracts\Permission $permission, ?string $guardName = null): bool
    {
        try {
            return $this->spatieHasPermissionTo($permission, $guardName);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
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
     */
    public function isBanned(): bool
    {
        return $this->bans()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Check if the user is jailed (has the Jailed role).
     */
    public function isJailed(): bool
    {
        return $this->hasRole('Jailed');
    }

    /**
     * @return HasMany<\App\Models\Ban, $this>
     */
    public function bans(): HasMany
    {
        return $this->hasMany(Ban::class);
    }

    /**
     * Get the user's display name (nickname or name).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ?? $this->name;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
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
     * @return array{thumb: string, small: string, medium: string, original: string}|null
     */
    public function getAvatarUrlsAttribute(): ?array
    {
        $media = $this->getFirstMedia('avatar');

        if (! $media) {
            return null;
        }

        $expiration = now()->addHours(24);

        return [
            'thumb' => URL::signedRoute('api.media.serve', ['media' => $media->id, 'conversion' => 'thumb'], $expiration),
            'small' => URL::signedRoute('api.media.serve', ['media' => $media->id, 'conversion' => 'small'], $expiration),
            'medium' => URL::signedRoute('api.media.serve', ['media' => $media->id, 'conversion' => 'medium'], $expiration),
            'original' => URL::signedRoute('api.media.serve', ['media' => $media->id], $expiration),
        ];
    }
}
