<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Role;
use App\Models\User;
use App\Services\AfkChannelService;
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
        // Seed all permissions and roles via Spatie
        $this->call(RolesAndPermissionsSeeder::class);

        $this->createDefaultChannels();
        $this->createAdminUser();
    }

    private function createDefaultChannels(): void
    {
        $generalCategory = Category::firstOrCreate(['name' => 'General'], ['position' => 0]);
        $this->createChannel($generalCategory, 'general', 'General discussion', 0);
        $this->createChannel($generalCategory, 'off-topic', 'Anything goes', 1);

        $voiceCategory = Category::firstOrCreate(['name' => 'Voice Channels'], ['position' => 2]);
        $this->createVoiceChannel($voiceCategory, 'General', 'Hang out and chat', 0);

        AfkChannelService::ensure();
    }

    private function createAdminUser(): void
    {
        if (User::where('username', 'admin')->exists()) {
            return;
        }

        $admin = User::create([
            'username' => 'admin',
            'email' => 'admin@laradisco.local',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
            'must_setup' => true,
        ]);

        $everyoneRole = Role::where('is_default', true)->first();
        $ownerRole = Role::where('name', 'Owner')->first();

        $rolesToAssign = array_filter([$everyoneRole, $ownerRole]);
        if (! empty($rolesToAssign)) {
            $admin->assignRole($rolesToAssign);
        }
    }

    private function createChannel(Category $category, string $name, string $topic, int $position): Channel
    {
        return Channel::firstOrCreate(
            ['category_id' => $category->id, 'slug' => Str::slug($name)],
            ['name' => $name, 'topic' => $topic, 'type' => 'text', 'position' => $position],
        );
    }

    private function createVoiceChannel(Category $category, string $name, string $topic, int $position): Channel
    {
        return Channel::firstOrCreate(
            ['category_id' => $category->id, 'slug' => Str::slug($name)],
            ['name' => $name, 'topic' => $topic, 'type' => 'voice', 'position' => $position],
        );
    }
}
