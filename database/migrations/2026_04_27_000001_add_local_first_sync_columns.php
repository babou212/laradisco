<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->uuid('client_temp_id')->nullable()->after('sender_device_id');
            // Per-(channel, sender) idempotency: same client retrying must
            // collapse to the same row. Nullable column → NULL rows excluded
            // from the unique check (NULLs are distinct).
            $table->unique(['channel_id', 'user_id', 'client_temp_id'], 'messages_channel_user_temp_unique');
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->uuid('client_temp_id')->nullable()->after('sender_device_id');
            $table->unique(['direct_message_group_id', 'user_id', 'client_temp_id'], 'dms_group_user_temp_unique');
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->unsignedBigInteger('version')->default(0)->after('position');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('version')->default(0)->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_channel_user_temp_unique');
            $table->dropColumn('client_temp_id');
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropUnique('dms_group_user_temp_unique');
            $table->dropColumn('client_temp_id');
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('version');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
