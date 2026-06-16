<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support hard-deleting a user while preserving their messages.
 *
 * When an admin (or the user themselves) deletes an account, the user row is
 * removed entirely but their messages must remain visible, attributed to a
 * "<username> (deleted)" tombstone. We therefore:
 *   - switch message/reaction `user_id` foreign keys to ON DELETE SET NULL so
 *     content survives the user row being removed, and
 *   - snapshot the author's username onto each message via `deleted_author_name`
 *     so the original (now-gone) identity can still be rendered.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Messages: keep the row, null the author, snapshot the username.
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('deleted_author_name')->nullable()->after('user_id');
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('direct_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('deleted_author_name')->nullable()->after('user_id');
        });

        // Reactions: keep the row (counts persist), just drop the author link.
        Schema::table('message_reactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('message_reactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('direct_message_reactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('direct_message_reactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('deleted_author_name');
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('deleted_author_name');
        });
        Schema::table('direct_messages', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('message_reactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('direct_message_reactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
