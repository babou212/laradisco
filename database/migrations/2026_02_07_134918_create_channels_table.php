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
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('topic')->nullable();
            $table->string('type', 20)->default('text');
            $table->integer('position')->default(0);
            $table->boolean('is_private')->default(false);
            $table->integer('slowmode_seconds')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'slug']);
            $table->index('position');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
