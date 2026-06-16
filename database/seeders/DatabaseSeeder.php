<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed all permissions and roles via Spatie
        $this->call(RolesAndPermissionsSeeder::class);

        $everyoneRole = Role::where('is_default', true)->first();
        $adminRole = Role::where('name', 'Admin')->first();

        // Create admin user
        $admin = User::factory()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole([$everyoneRole, $adminRole]);

        // Create specific test users
        $testUsers = [
            ['username' => 'johndoe', 'email' => 'john@example.com'],
            ['username' => 'janesmith', 'email' => 'jane@example.com'],
            ['username' => 'bobjohnson', 'email' => 'bob@example.com'],
            ['username' => 'alicew', 'email' => 'alice@example.com'],
            ['username' => 'charlieb', 'email' => 'charlie@example.com'],
        ];

        foreach ($testUsers as $userData) {
            $user = User::factory()->create($userData);
            $user->assignRole($everyoneRole);
        }

        // Create random test users
        $users = User::factory(20)->create();
        foreach ($users as $user) {
            $user->assignRole($everyoneRole);
        }

        // Create default categories and channels
        $generalCategory = Category::create(['name' => 'General', 'position' => 0]);
        $this->createChannel($generalCategory, 'general', 'General discussion', 0);
        $this->createChannel($generalCategory, 'introductions', 'Introduce yourself!', 1);
        $this->createChannel($generalCategory, 'off-topic', 'Anything goes', 2);

        $infoCategory = Category::create(['name' => 'Information', 'position' => 1]);
        $this->createChannel($infoCategory, 'announcements', 'Server announcements', 0);
        $this->createChannel($infoCategory, 'rules', 'Server rules and guidelines', 1);

        $voiceCategory = Category::create(['name' => 'Voice Channels', 'position' => 2]);
        $this->createChannel($voiceCategory, 'General Voice', 'Hang out and chat', 0, 'voice');
        $this->createChannel($voiceCategory, 'Gaming', 'Voice chat for gaming', 1, 'voice');
        $this->createChannel($voiceCategory, 'Music', 'Listen to music together', 2, 'voice');
    }

    private function createChannel(Category $category, string $name, string $topic, int $position, string $type = 'text'): Channel
    {
        return Channel::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'topic' => $topic,
            'type' => $type,
            'position' => $position,
        ]);
    }
}
