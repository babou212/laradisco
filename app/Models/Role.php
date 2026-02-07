<?php

namespace App\Models;

use App\Concerns\ClearsCaches;
use App\Enums\PermissionFlag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /** @use HasFactory<\Database\Factories\RoleFactory> */
    use ClearsCaches, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'color',
        'is_hoisted',
        'position',
        'permissions',
        'is_mentionable',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_hoisted' => 'boolean',
            'is_mentionable' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Check if this role has a specific permission.
     */
    public function hasPermission(PermissionFlag $permission): bool
    {
        if (in_array(PermissionFlag::Administrator->value, $this->permissions ?? [], true)) {
            return true;
        }

        return in_array($permission->value, $this->permissions ?? [], true);
    }

    /**
     * Grant a permission to this role.
     */
    public function grantPermission(PermissionFlag $permission): void
    {
        $permissions = $this->permissions ?? [];

        if (! in_array($permission->value, $permissions, true)) {
            $permissions[] = $permission->value;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    /**
     * Revoke a permission from this role.
     */
    public function revokePermission(PermissionFlag $permission): void
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_values(array_filter($permissions, fn (string $p) => $p !== $permission->value));
        $this->permissions = $permissions;
        $this->save();
    }

    /**
     * Clear caches related to this role.
     */
    public function clearCaches(): void
    {
        // Clear permission caches for all users with this role
        foreach ($this->users as $user) {
            cache()->forget("user.{$user->id}.permissions");
        }
    }
}
