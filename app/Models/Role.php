<?php

namespace App\Models;

use App\Concerns\ClearsCaches;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * @property string $name
 * @property string|null $color
 * @property bool $is_hoisted
 * @property int $position
 * @property bool $is_mentionable
 * @property bool $is_default
 */
class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use ClearsCaches, HasFactory;

    /**
     * Override Spatie's users() to explicitly reference the User model.
     *
     * Spatie's default implementation uses getModelForGuard() which can
     * return NULL when the config is cached or the guard resolution fails.
     */
    /**
     * @return MorphToMany<User, $this>
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key'),
        );
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'color',
        'is_hoisted',
        'position',
        'is_mentionable',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_hoisted' => 'boolean',
            'is_mentionable' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Clear caches related to this role.
     */
    public function clearCaches(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
