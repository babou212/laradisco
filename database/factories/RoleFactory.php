<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'name' => 'Role',
            'guard_name' => 'web',
            'color' => '#000000',
            'is_hoisted' => false,
            'position' => 0,
            'is_mentionable' => true,
            'is_default' => false,
        ];
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
