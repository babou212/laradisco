<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_prekeys', function (Blueprint $table) {
            $table->id();
            $table->char('device_id', 36);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('prekey_id');
            $table->binary('public_key');
            $table->boolean('used')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->unique(['device_id', 'prekey_id']);
            $table->index(['device_id', 'used']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_prekeys');
    }
};
