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
        // Create default roles
        $everyoneRole = Role::firstOrCreate(
            ['name' => 'everyone'],
            Role::factory()->everyone()->make()->toArray()
        );
        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin'],
            Role::factory()->admin()->make()->toArray()
        );
        $moderatorRole = Role::firstOrCreate(
            ['name' => 'Moderator'],
            Role::factory()->moderator()->make()->toArray()
        );

        // Create admin user
        $admin = User::factory()->create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
        ]);
        $admin->roles()->attach([$everyoneRole->id, $adminRole->id]);

        // Create specific test users
        $testUsers = [
            ['name' => 'John Doe', 'username' => 'johndoe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'username' => 'janesmith', 'email' => 'jane@example.com'],
            ['name' => 'Bob Johnson', 'username' => 'bobjohnson', 'email' => 'bob@example.com'],
            ['name' => 'Alice Williams', 'username' => 'alicew', 'email' => 'alice@example.com'],
            ['name' => 'Charlie Brown', 'username' => 'charlieb', 'email' => 'charlie@example.com'],
        ];

        foreach ($testUsers as $userData) {
            $user = User::factory()->create($userData);
            $user->roles()->attach($everyoneRole->id);
        }

        // Create random test users
        $users = User::factory(20)->create();
        foreach ($users as $user) {
            $user->roles()->attach($everyoneRole->id);
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

    /**
     * Create a channel within a category.
     */
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
