<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['sender_device_id', 'history_ciphertext', 'message_bytes', 'epoch']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->text('content')->nullable()->after('reply_to_id');
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropColumn(['sender_device_id', 'history_ciphertext', 'message_bytes', 'epoch']);
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->text('content')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('content');
            $table->char('sender_device_id', 36)->nullable();
            $table->text('history_ciphertext')->nullable();
            $table->text('message_bytes')->nullable();
            $table->unsignedBigInteger('epoch')->default(0);
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropColumn('content');
            $table->char('sender_device_id', 36)->nullable();
            $table->text('history_ciphertext')->nullable();
            $table->text('message_bytes')->nullable();
            $table->unsignedBigInteger('epoch')->default(0);
        });
    }
};
