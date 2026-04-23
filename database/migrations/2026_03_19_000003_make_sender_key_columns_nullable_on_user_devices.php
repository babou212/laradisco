<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->text('device_identity_key')->nullable()->change();
            $table->text('identity_signature')->nullable()->change();
            $table->text('signed_prekey')->nullable()->change();
            $table->unsignedInteger('signed_prekey_id')->nullable()->change();
            $table->text('signed_prekey_signature')->nullable()->change();
            $table->timestamp('signed_prekey_timestamp')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->text('device_identity_key')->nullable(false)->change();
            $table->text('identity_signature')->nullable(false)->change();
            $table->text('signed_prekey')->nullable(false)->change();
            $table->unsignedInteger('signed_prekey_id')->nullable(false)->change();
            $table->text('signed_prekey_signature')->nullable(false)->change();
            $table->timestamp('signed_prekey_timestamp')->nullable(false)->change();
        });
    }
};
