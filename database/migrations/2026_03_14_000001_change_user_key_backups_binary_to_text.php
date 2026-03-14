<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: Binary (bytea) columns corrupt base64 strings in PostgreSQL.
 * The client sends base64-encoded strings, not raw binary data,
 * so these columns should be text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_key_backups', function (Blueprint $table) {
            $table->text('encrypted_bundle')->change();
            $table->string('salt', 128)->change();
            $table->string('nonce', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_key_backups', function (Blueprint $table) {
            $table->binary('encrypted_bundle')->change();
            $table->binary('salt')->change();
            $table->binary('nonce')->change();
        });
    }
};
