<?php

namespace Database\Factories;

use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DirectMessage>
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
            'message_bytes' => fake()->paragraph(),
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
