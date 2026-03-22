<?php

namespace Database\Factories;

use App\Models\Mention;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mention>
 */
class MentionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'user_id' => User::factory(),
            'type' => 'user',
        ];
    }

    /**
     * Mark the mention as an @everyone mention.
     */
    public function everyone(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'type' => 'everyone',
        ]);
    }

    /**
     * Mark the mention as an @here mention.
     */
    public function here(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'type' => 'here',
        ]);
    }
}
