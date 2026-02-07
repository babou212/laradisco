<?php

namespace Database\Factories;

use App\Models\DirectMessageGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DirectMessage>
 */
class DirectMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'direct_message_group_id' => DirectMessageGroup::factory(),
            'user_id' => User::factory(),
            'content' => fake()->paragraph(),
            'is_edited' => false,
            'edited_at' => null,
        ];
    }

    /**
     * Mark the message as edited.
     */
    public function edited(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_edited' => true,
            'edited_at' => now(),
        ]);
    }
}
