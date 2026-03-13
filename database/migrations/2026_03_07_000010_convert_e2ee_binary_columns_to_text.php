<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_identity_keys', function (Blueprint $table) {
            $table->text('identity_key')->change();
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $table->text('device_identity_key')->change();
            $table->text('identity_signature')->change();
            $table->text('signed_prekey')->change();
            $table->text('signed_prekey_signature')->change();
        });

        Schema::table('device_prekeys', function (Blueprint $table) {
            $table->text('public_key')->change();
        });

        Schema::table('user_key_backups', function (Blueprint $table) {
            $table->text('encrypted_bundle')->change();
            $table->text('salt')->change();
            $table->text('nonce')->change();
        });

        Schema::table('e2ee_audit_log', function (Blueprint $table) {
            $table->text('public_key')->nullable()->change();
            $table->text('signature')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_identity_keys', function (Blueprint $table) {
            $table->binary('identity_key')->change();
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $table->binary('device_identity_key')->change();
            $table->binary('identity_signature')->change();
            $table->binary('signed_prekey')->change();
            $table->binary('signed_prekey_signature')->change();
        });

        Schema::table('device_prekeys', function (Blueprint $table) {
            $table->binary('public_key')->change();
        });

        Schema::table('user_key_backups', function (Blueprint $table) {
            $table->binary('encrypted_bundle')->change();
            $table->binary('salt')->change();
            $table->binary('nonce')->change();
        });

        Schema::table('e2ee_audit_log', function (Blueprint $table) {
            $table->binary('public_key')->nullable()->change();
            $table->binary('signature')->nullable()->change();
        });
    }
};
