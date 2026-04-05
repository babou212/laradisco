<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    /**
     * Seed the production database with default roles, channels, and admin user.
     * This seeder is idempotent — safe to run multiple times.
     */
    public function run(): void
    {
        $this->createRoles();
        $this->createDefaultChannels();
        $this->createAdminUser();
    }

    /**
     * Create the default roles if they don't exist.
     */
    private function createRoles(): void
    {
        if (! Role::where('is_default', true)->exists()) {
            Role::factory()->everyone()->create();
        }

        if (! Role::where('name', 'Admin')->exists()) {
            Role::factory()->admin()->create();
        }

        if (! Role::where('name', 'Moderator')->exists()) {
            Role::factory()->moderator()->create();
        }
    }

    /**
     * Create the default categories and channels if they don't exist.
     */
    private function createDefaultChannels(): void
    {
        $generalCategory = Category::firstOrCreate(['name' => 'General'], ['position' => 0]);
        $this->createChannel($generalCategory, 'general', 'General discussion', 0);

        $voiceCategory = Category::firstOrCreate(['name' => 'Voice Channels'], ['position' => 2]);
        $this->createVoiceChannel($voiceCategory, 'General', 'Hang out and chat', 0);
    }

    /**
     * Create the default admin user if no admin exists.
     */
    private function createAdminUser(): void
    {
        if (User::where('username', 'admin')->exists()) {
            return;
        }

        $admin = User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@laradisco.local',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
            'must_setup' => true,
        ]);

        $everyoneRole = Role::where('is_default', true)->first();
        $adminRole = Role::where('name', 'Admin')->first();

        $admin->roles()->attach(array_filter([
            $everyoneRole?->id,
            $adminRole?->id,
        ]));
    }

    /**
     * Create a text channel within a category if it does not already exist.
     */
    private function createChannel(Category $category, string $name, string $topic, int $position): Channel
    {
        return Channel::firstOrCreate(
            ['category_id' => $category->id, 'slug' => Str::slug($name)],
            ['name' => $name, 'topic' => $topic, 'type' => 'text', 'position' => $position],
        );
    }

    /**
     * Create a voice channel within a category if it does not already exist.
     */
    private function createVoiceChannel(Category $category, string $name, string $topic, int $position): Channel
    {
        return Channel::firstOrCreate(
            ['category_id' => $category->id, 'slug' => Str::slug($name)],
            ['name' => $name, 'topic' => $topic, 'type' => 'voice', 'position' => $position],
        );
    }
}
