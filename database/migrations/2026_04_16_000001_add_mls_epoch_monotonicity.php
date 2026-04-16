<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DELETE FROM mls_messages m1
            USING mls_messages m2
            WHERE m1.group_id     = m2.group_id
              AND m1.epoch        = m2.epoch
              AND m1.message_type = 'commit'
              AND m2.message_type = 'commit'
              AND m1.id > m2.id
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX mls_msg_group_epoch_commit_unique
            ON mls_messages (group_id, epoch)
            WHERE message_type = 'commit'
        SQL);

        Schema::table('mls_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('current_epoch')->default(0)->after('creator_device_id');
        });

        DB::statement(<<<'SQL'
            UPDATE mls_groups g
            SET current_epoch = COALESCE((
                SELECT MAX(m.epoch)
                FROM mls_messages m
                WHERE m.group_id     = g.group_id
                  AND m.message_type = 'commit'
            ), 0)
        SQL);
    }

    public function down(): void
    {
        Schema::table('mls_groups', function (Blueprint $table) {
            $table->dropColumn('current_epoch');
        });

        DB::statement('DROP INDEX IF EXISTS mls_msg_group_epoch_commit_unique');
    }
};
