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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7)->default('#99AAB5');
            $table->boolean('is_hoisted')->default(false);
            $table->integer('position')->default(0);
            $table->json('permissions')->default('[]');
            $table->boolean('is_mentionable')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('position');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
