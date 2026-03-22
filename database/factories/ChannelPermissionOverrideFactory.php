<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\ChannelPermissionOverride;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelPermissionOverride>
 */
class ChannelPermissionOverrideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'role_id' => Role::factory(),
            'user_id' => null,
            'allow' => [],
            'deny' => [],
        ];
    }
}
