<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->string('platform', 32)->nullable()->after('device_name');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->char('device_id', 36)->nullable()->index()->after('abilities');
        });
    }

    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropColumn('platform');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['device_id']);
            $table->dropColumn('device_id');
        });
    }
};
