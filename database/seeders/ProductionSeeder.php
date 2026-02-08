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
        if (Category::exists()) {
            return;
        }

        $generalCategory = Category::create(['name' => 'General', 'position' => 0]);
        $this->createChannel($generalCategory, 'general', 'General discussion', 0);
        $this->createChannel($generalCategory, 'introductions', 'Introduce yourself!', 1);

        $infoCategory = Category::create(['name' => 'Information', 'position' => 1]);
        $this->createChannel($infoCategory, 'announcements', 'Server announcements', 0);
        $this->createChannel($infoCategory, 'rules', 'Server rules and guidelines', 1);
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
     * Create a text channel within a category.
     */
    private function createChannel(Category $category, string $name, string $topic, int $position): Channel
    {
        return Channel::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'topic' => $topic,
            'type' => 'text',
            'position' => $position,
        ]);
    }
}
