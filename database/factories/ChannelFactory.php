<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Channel>
 */
class ChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'topic' => fake()->optional()->sentence(),
            'type' => 'text',
            'position' => fake()->numberBetween(0, 50),
            'is_private' => false,
            'slowmode_seconds' => 0,
        ];
    }

    /**
     * Mark the channel as private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }

    /**
     * Set slowmode on the channel.
     */
    public function withSlowmode(int $seconds = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'slowmode_seconds' => $seconds,
        ]);
    }
}
