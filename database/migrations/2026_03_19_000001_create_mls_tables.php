<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mls_key_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('device_id', 36);
            $table->text('key_package_bytes');
            $table->string('key_package_hash', 64)->unique();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'device_id', 'consumed_at'], 'mls_kp_user_device_available');
        });

        Schema::create('mls_messages', function (Blueprint $table) {
            $table->id();
            $table->string('group_id', 255)->index();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->char('sender_device_id', 36);
            $table->enum('message_type', ['commit', 'proposal', 'application']);
            $table->text('message_bytes');
            $table->unsignedBigInteger('epoch');
            $table->timestamps();

            $table->index(['group_id', 'epoch'], 'mls_msg_group_epoch');
        });

        Schema::create('mls_welcome_messages', function (Blueprint $table) {
            $table->id();
            $table->string('group_id', 255);
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->char('recipient_device_id', 36);
            $table->text('welcome_bytes');
            $table->text('ratchet_tree_bytes');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_user_id', 'recipient_device_id', 'consumed_at'], 'mls_welcome_recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mls_welcome_messages');
        Schema::dropIfExists('mls_messages');
        Schema::dropIfExists('mls_key_packages');
    }
};
