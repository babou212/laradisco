<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Re-introduce the E2EE / MLS schema that was dropped by
 * 2026_05_02_000001_drop_e2ee_tables.php when e2ee was reverted.
 *
 * Rather than hand-copying the DDL, we re-run the original create/alter
 * migrations' up() methods (still present in this directory) in dependency
 * order — they are the exact source of truth for the final table shapes.
 * Attachment tables (encrypted_attachments / message_attachments) are
 * intentionally NOT restored: DM attachments stay on the current Spatie
 * media table this iteration.
 */
return new class extends Migration
{
    /**
     * Original create/alter migrations to replay, in order.
     *
     * @var list<string>
     */
    private array $sources = [
        '2026_03_07_000001_create_user_identity_keys_table.php',
        '2026_03_07_000002_create_user_devices_table.php',
        '2026_03_07_000005_create_user_key_backups_table.php',
        '2026_03_07_000006_create_e2ee_audit_log_table.php',
        '2026_03_07_000010_convert_e2ee_binary_columns_to_text.php',
        '2026_03_14_000001_change_user_key_backups_binary_to_text.php',
        '2026_03_19_000001_create_mls_tables.php',
        '2026_03_19_000003_make_sender_key_columns_nullable_on_user_devices.php',
        '2026_03_23_000001_create_mls_groups_table.php',
        '2026_03_24_000001_create_mls_join_requests_table.php',
        '2026_03_25_000001_add_lockout_columns_to_user_key_backups_table.php',
        '2026_04_16_000001_add_mls_epoch_monotonicity.php',
    ];

    public function up(): void
    {
        if (Schema::hasTable('user_identity_keys')) {
            return;
        }

        foreach ($this->sources as $file) {
            $migration = require database_path("migrations/{$file}");
            $migration->up();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mls_join_requests');
        Schema::dropIfExists('mls_welcome_messages');
        Schema::dropIfExists('mls_messages');
        Schema::dropIfExists('mls_key_packages');
        Schema::dropIfExists('mls_groups');
        Schema::dropIfExists('e2ee_audit_log');
        Schema::dropIfExists('user_key_backups');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('user_identity_keys');
    }
};
