<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DirectMessageGroup>
 */
class DirectMessageGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => null,
            'icon_path' => null,
            'last_message_at' => null,
        ];
    }

    /**
     * Create a named group DM.
     */
    public function named(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->words(3, true),
        ]);
    }
}
