<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encrypted_search_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_type', 10); // 'channel' or 'dm'
            $table->unsignedBigInteger('conversation_id');
            $table->char('token', 64); // HMAC-SHA-256 hex (first 32 bytes = 64 hex chars)
            $table->unsignedBigInteger('message_id');
            $table->timestamp('created_at')->nullable();

            // Primary lookup: find messages matching a token in a conversation
            $table->index(
                ['conversation_type', 'conversation_id', 'token'],
                'idx_sse_lookup',
            );

            // Cleanup: delete tokens when a message is deleted/edited
            $table->index('message_id', 'idx_sse_message');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encrypted_search_tokens');
    }
};
