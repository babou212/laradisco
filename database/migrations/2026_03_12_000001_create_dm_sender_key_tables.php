<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dm_sender_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dm_group_id')->constrained('direct_message_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('device_id', 36);
            $table->char('distribution_id', 36);
            $table->timestamps();

            $table->unique(['dm_group_id', 'user_id', 'device_id']);
        });

        Schema::create('dm_sender_key_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dm_group_id')->constrained('direct_message_groups')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->char('sender_device_id', 36);
            $table->char('distribution_id', 36);
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->char('recipient_device_id', 36);
            $table->text('encrypted_distribution');
            $table->text('ephemeral_public_key');
            $table->text('nonce');
            $table->timestamps();

            $table->unique(
                ['dm_group_id', 'sender_device_id', 'distribution_id', 'recipient_device_id'],
                'dm_skd_unique',
            );

            $table->index(
                ['recipient_user_id', 'recipient_device_id', 'dm_group_id'],
                'dm_skd_recipient',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dm_sender_key_distributions');
        Schema::dropIfExists('dm_sender_keys');
    }
};
