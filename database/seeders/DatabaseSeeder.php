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
        $everyoneRole = Role::factory()->everyone()->create();
        $adminRole = Role::factory()->admin()->create();
        $moderatorRole = Role::factory()->moderator()->create();

        // Create admin user
        $admin = User::factory()->create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
        ]);
        $admin->roles()->attach([$everyoneRole->id, $adminRole->id]);

        // Create test users
        $users = User::factory(10)->create();
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
