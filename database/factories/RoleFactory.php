<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Role '.$this->faker->unique()->uuid(),
            'guard_name' => 'web',
            'color' => '#000000',
            'is_hoisted' => false,
            'position' => 0,
            'is_mentionable' => true,
            'is_default' => false,
        ];
    }

    /**
     * Accept a virtual `permissions` attribute and apply it via Spatie's
     * syncPermissions after the role is persisted. The roles table has no
     * permissions column — permissions live in the role_has_permissions pivot.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        $pendingPermissions = null;
        if (is_array($attributes) && array_key_exists('permissions', $attributes)) {
            $pendingPermissions = $attributes['permissions'];
            unset($attributes['permissions']);
        }

        $result = parent::create($attributes, $parent);

        if ($pendingPermissions !== null) {
            foreach ($pendingPermissions as $name) {
                Permission::findOrCreate($name, 'web');
            }

            $roles = $result instanceof Collection ? $result : collect([$result]);
            foreach ($roles as $role) {
                $role->syncPermissions($pendingPermissions);
            }
        }

        return $result;
    }

    /**
     * Create the @everyone default role.
     */
    public function everyone(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'everyone',
            'is_default' => true,
            'position' => 0,
        ]);
    }

    /**
     * Create an admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Admin',
            'color' => '#E74C3C',
            'is_hoisted' => true,
            'position' => 100,
        ]);
    }

    /**
     * Create a moderator role.
     */
    public function moderator(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Moderator',
            'color' => '#3498DB',
            'is_hoisted' => true,
            'position' => 50,
        ]);
    }
}
