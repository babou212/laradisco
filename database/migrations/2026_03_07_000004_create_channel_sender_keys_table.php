<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_sender_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('device_id', 36);
            $table->char('distribution_id', 36);
            $table->timestamps();

            $table->unique(['channel_id', 'user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_sender_keys');
    }
};
