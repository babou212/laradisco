<?php

namespace Database\Factories;

use App\Enums\AttachmentStatus;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'storage_path' => fake()->uuid().'/file',
            'file_name' => fake()->word().'.png',
            'mime_type' => 'image/png',
            'size' => fake()->numberBetween(1024, 1024 * 1024),
            'thumbnail_path' => null,
            'thumbnail_size' => null,
            'width' => 800,
            'height' => 600,
            'status' => AttachmentStatus::Attached,
        ];
    }

    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'width' => null,
            'height' => null,
        ]);
    }
}
