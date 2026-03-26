<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mls_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_id', 255)->unique();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->char('creator_device_id', 36);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mls_groups');
    }
};
