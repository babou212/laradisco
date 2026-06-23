<?php

namespace Database\Factories;

use App\Models\SoundboardSound;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SoundboardSound>
 */
class SoundboardSoundFactory extends Factory
{
    protected $model = SoundboardSound::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'user_id' => User::factory(),
            'duration_ms' => fake()->numberBetween(500, 10000),
        ];
    }
}
