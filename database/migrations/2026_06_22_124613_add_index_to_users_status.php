<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `users.status` is filtered on in the @here notification fan-out
     * (MentionService::notifyHere) where it would otherwise force a full table
     * scan once the user base grows.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
