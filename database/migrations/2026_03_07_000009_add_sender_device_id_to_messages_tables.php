<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->char('sender_device_id', 36)->nullable()->after('is_encrypted');
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->char('sender_device_id', 36)->nullable()->after('is_encrypted');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('sender_device_id');
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropColumn('sender_device_id');
        });
    }
};
