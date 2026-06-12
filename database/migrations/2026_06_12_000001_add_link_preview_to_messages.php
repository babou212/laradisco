<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->json('link_preview')->nullable();
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->json('link_preview')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('link_preview');
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropColumn('link_preview');
        });
    }
};
