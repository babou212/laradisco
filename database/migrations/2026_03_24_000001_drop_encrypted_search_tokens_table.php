<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('encrypted_search_tokens');
    }

    public function down(): void
    {
        Schema::create('encrypted_search_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_type', 10);
            $table->unsignedBigInteger('conversation_id');
            $table->char('token', 64);
            $table->unsignedBigInteger('message_id');
            $table->timestamp('created_at')->nullable();

            $table->index(
                ['conversation_type', 'conversation_id', 'token'],
                'idx_sse_lookup',
            );

            $table->index('message_id', 'idx_sse_message');
        });
    }
};
