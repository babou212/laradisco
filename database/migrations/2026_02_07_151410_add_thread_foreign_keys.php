<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('thread_id')->references('id')->on('threads')->cascadeOnDelete();
        });

        Schema::table('threads', function (Blueprint $table) {
            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['thread_id']);
        });

        Schema::table('threads', function (Blueprint $table) {
            $table->dropForeign(['message_id']);
        });
    }
};
