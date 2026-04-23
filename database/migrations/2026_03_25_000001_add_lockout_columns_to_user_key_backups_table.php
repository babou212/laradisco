<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_key_backups', function (Blueprint $table) {
            $table->unsignedInteger('failed_attempt_count')->default(0)->after('version');
            $table->timestamp('locked_at')->nullable()->after('failed_attempt_count');
            $table->timestamp('last_attempt_at')->nullable()->after('locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_key_backups', function (Blueprint $table) {
            $table->dropColumn(['failed_attempt_count', 'locked_at', 'last_attempt_at']);
        });
    }
};
