<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mls_join_requests', function (Blueprint $table) {
            $table->id();
            $table->string('group_id', 255);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('device_id', 36);
            $table->enum('status', ['pending', 'fulfilled', 'expired'])->default('pending');
            $table->timestamps();

            $table->unique(['group_id', 'device_id']);
            $table->index(['group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mls_join_requests');
    }
};
