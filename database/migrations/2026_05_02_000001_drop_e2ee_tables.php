<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('mls_join_requests');
        Schema::dropIfExists('mls_welcome_messages');
        Schema::dropIfExists('mls_messages');
        Schema::dropIfExists('mls_key_packages');
        Schema::dropIfExists('mls_groups');
        Schema::dropIfExists('e2ee_audit_log');
        Schema::dropIfExists('user_key_backups');
        Schema::dropIfExists('encrypted_attachments');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('user_identity_keys');
    }

    public function down(): void
    {
        // irreversible — see original create migrations under 2026_03_07_*, 2026_03_19_*, 2026_03_23_*, 2026_03_24_*, 2026_03_26_*
    }
};
