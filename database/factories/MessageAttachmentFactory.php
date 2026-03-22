<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageAttachment>
 */
class MessageAttachmentFactory extends Factory
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
            'original_name' => fake()->word().'.png',
            'file_path' => 'attachments/'.fake()->uuid().'.png',
            'mime_type' => 'image/png',
            'file_size' => fake()->numberBetween(1024, 10485760),
            'width' => fake()->optional()->numberBetween(100, 1920),
            'height' => fake()->optional()->numberBetween(100, 1080),
        ];
    }

    /**
     * Create a document attachment.
     */
    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_name' => fake()->word().'.pdf',
            'file_path' => 'attachments/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'width' => null,
            'height' => null,
        ]);
    }
}
