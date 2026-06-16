<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('message_type', 20); // 'channel' | 'direct_message'
            $table->unsignedBigInteger('message_id');
            $table->json('payload'); // the broadcast `message` object
            $table->timestamp('created_at')->nullable();

            // Idempotent enqueue + ack: one inbox row per (recipient, type, message).
            $table->unique(['user_id', 'message_type', 'message_id'], 'inbox_unique_recipient_message');

            // Drain query: WHERE user_id = ? ORDER BY id.
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
    }
};
