<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('sender_key_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
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
                ['channel_id', 'sender_device_id', 'distribution_id', 'recipient_device_id'],
                'skd_unique',
            );

            $table->index(['recipient_user_id', 'recipient_device_id', 'channel_id'], 'skd_recipient_channel');
        });
    }
};
