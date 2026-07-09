<?php

namespace Database\Seeders;

use App\Enums\PermissionFlag;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed all permissions from PermissionFlag enum, then create
     * Default roles: Owner, Admin, Moderator, Member (everyone), Jailed.
     *
     * Idempotent — safe to run multiple times.
     */
    public function run(): void
    {
        $this->call(PermissionsSeeder::class);

        // --- Owner role (full access) ---
        $owner = Role::firstOrCreate(
            ['name' => 'Owner', 'guard_name' => 'web'],
            [
                'color' => '#F1C40F',
                'is_hoisted' => true,
                'position' => 200,
                'is_mentionable' => false,
                'is_default' => false,
            ]
        );
        $owner->syncPermissions([PermissionFlag::Administrator->value]);

        // --- Admin role ---
        $admin = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web'],
            [
                'color' => '#E74C3C',
                'is_hoisted' => true,
                'position' => 100,
                'is_mentionable' => true,
                'is_default' => false,
            ]
        );
        $admin->syncPermissions([PermissionFlag::Administrator->value]);

        // --- Moderator role ---
        $moderator = Role::firstOrCreate(
            ['name' => 'Moderator', 'guard_name' => 'web'],
            [
                'color' => '#3498DB',
                'is_hoisted' => true,
                'position' => 50,
                'is_mentionable' => true,
                'is_default' => false,
            ]
        );
        $moderator->syncPermissions([
            PermissionFlag::ManageMessages->value,
            PermissionFlag::ManageThreads->value,
            PermissionFlag::KickMembers->value,
            PermissionFlag::BanMembers->value,
            PermissionFlag::PinMessages->value,
            PermissionFlag::MuteMembers->value,
            PermissionFlag::DeafenMembers->value,
            PermissionFlag::MoveMembers->value,
            PermissionFlag::ViewChannels->value,
            PermissionFlag::SendMessages->value,
            PermissionFlag::ReadMessageHistory->value,
            PermissionFlag::Connect->value,
            PermissionFlag::Speak->value,
            PermissionFlag::Video->value,
            PermissionFlag::EmbedLinks->value,
            PermissionFlag::AttachFiles->value,
            PermissionFlag::AddReactions->value,
            PermissionFlag::CreateThreads->value,
            PermissionFlag::SendThreadMessages->value,
        ]);

        // --- everyone (default role for all members) ---
        $everyone = Role::firstOrCreate(
            ['name' => 'everyone', 'guard_name' => 'web'],
            [
                'color' => '#99AAB5',
                'is_hoisted' => false,
                'position' => 0,
                'is_mentionable' => false,
                'is_default' => true,
            ]
        );
        $everyone->syncPermissions(
            array_map(fn (PermissionFlag $p) => $p->value, PermissionFlag::defaultEveryonePermissions())
        );

        // --- Jailed role (very restricted) ---
        $jailed = Role::firstOrCreate(
            ['name' => 'Jailed', 'guard_name' => 'web'],
            [
                'color' => '#95A5A6',
                'is_hoisted' => false,
                'position' => -1,
                'is_mentionable' => false,
                'is_default' => false,
            ]
        );
        // Jailed users can only view channels and read history — no sending
        $jailed->syncPermissions([
            PermissionFlag::ViewChannels->value,
            PermissionFlag::ReadMessageHistory->value,
        ]);
    }
}
