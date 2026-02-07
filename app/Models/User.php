<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PermissionFlag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
        'avatar_path',
        'nickname',
        'about_me',
        'custom_status',
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
            'two_factor_confirmed_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
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
     * Check if the user has a specific permission through any of their roles.
     */
    public function hasPermission(PermissionFlag $permission): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->hasPermission($permission));
    }

    /**
     * Check if the user has the Administrator permission.
     */
    public function isAdministrator(): bool
    {
        return $this->hasPermission(PermissionFlag::Administrator);
    }

    /**
     * Get the user's display name (nickname or name).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ?? $this->name;
    }
}
