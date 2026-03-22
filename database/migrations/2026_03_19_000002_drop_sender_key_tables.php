<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sender_key_distributions');
        Schema::dropIfExists('dm_sender_key_distributions');
        Schema::dropIfExists('channel_sender_keys');
        Schema::dropIfExists('dm_sender_keys');
        Schema::dropIfExists('device_prekeys');
    }

    public function down(): void
    {
        // These tables are no longer used after MLS migration.
        // Restoring them would require the original migration files.
    }
};
