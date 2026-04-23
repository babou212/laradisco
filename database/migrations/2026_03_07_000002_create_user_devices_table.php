<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('device_id', 36);
            $table->string('device_name')->nullable();
            $table->binary('device_identity_key');
            $table->binary('identity_signature');
            $table->binary('signed_prekey');
            $table->unsignedInteger('signed_prekey_id');
            $table->binary('signed_prekey_signature');
            $table->timestamp('signed_prekey_timestamp');
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
