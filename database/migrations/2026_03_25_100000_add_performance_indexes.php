<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index('reply_to_id');
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('message_reactions', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('direct_message_reactions', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('thread_followers', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['reply_to_id']);
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('message_reactions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('direct_message_reactions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('thread_followers', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['read_at']);
        });
    }
};
