<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allow `application` MLS messages (device-to-device history sync, non-chat).
 *
 * Fresh installs get the 3-value enum directly from the create migration
 * (2026_03_19_000001), so no column change is needed there — avoiding a sqlite
 * table-rebuild that would clobber the partial commit-epoch unique index.
 *
 * This migration only fixes databases that were already created with the old
 * 2-value enum: on Postgres it drops the stale CHECK constraint. (On sqlite the
 * enum is a table-level CHECK; existing sqlite DBs are dev-only and re-created
 * from scratch, so nothing to do there.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE mls_messages DROP CONSTRAINT IF EXISTS mls_messages_message_type_check');
        }
    }

    public function down(): void
    {
        // No-op: the enum is defined by the create migration.
    }
};
