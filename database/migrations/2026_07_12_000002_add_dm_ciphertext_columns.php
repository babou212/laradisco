<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-add MLS ciphertext columns to direct_messages so DM application messages
 * are stored encrypted while server-side keyset pagination (MessageWindowService)
 * keeps working over ciphertext rows.
 *
 * Unlike the original e2ee schema, `content` is kept (nullable) rather than
 * dropped — channel messages and existing plaintext code/tests still use it,
 * and this iteration only encrypts DMs. For an E2EE DM, `content` stays null
 * and the payload lives in `message_bytes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direct_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('direct_messages', 'sender_device_id')) {
                $table->char('sender_device_id', 36)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('direct_messages', 'message_bytes')) {
                $table->text('message_bytes')->nullable()->after('sender_device_id');
            }
            if (! Schema::hasColumn('direct_messages', 'history_ciphertext')) {
                $table->text('history_ciphertext')->nullable()->after('message_bytes');
            }
            if (! Schema::hasColumn('direct_messages', 'epoch')) {
                $table->unsignedBigInteger('epoch')->default(0)->after('history_ciphertext');
            }
        });
    }

    public function down(): void
    {
        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropColumn(['sender_device_id', 'message_bytes', 'history_ciphertext', 'epoch']);
        });
    }
};
