<?php

use App\Enums\PermissionFlag;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $voicePermissions = [
        'connect',
        'speak',
        'video',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $role = Role::where('is_default', true)->first();

        if (! $role) {
            return;
        }

        $permissions = $role->permissions ?? [];
        $updated = array_unique(array_merge($permissions, $this->voicePermissions));

        $role->update(['permissions' => array_values($updated)]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $role = Role::where('is_default', true)->first();

        if (! $role) {
            return;
        }

        $permissions = array_values(array_diff($role->permissions ?? [], $this->voicePermissions));

        $role->update(['permissions' => $permissions]);
    }
};
