<?php

namespace Database\Seeders;

use App\Enums\PermissionFlag;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Seed all permissions from the PermissionFlag enum.
     *
     * Idempotent — safe to run multiple times.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (PermissionFlag::cases() as $flag) {
            Permission::firstOrCreate([
                'name' => $flag->value,
                'guard_name' => 'web',
            ]);
        }
    }
}
