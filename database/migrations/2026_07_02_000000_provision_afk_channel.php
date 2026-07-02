<?php

use App\Models\ServerSetting;
use App\Services\AfkChannelService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Provision the dedicated AFK voice channel and wire it to the server
     * settings so it exists on every install without a manual step.
     */
    public function up(): void
    {
        AfkChannelService::ensure();
    }

    public function down(): void
    {
        $settings = ServerSetting::first();
        if ($settings && $settings->afk_channel_id) {
            $settings->afk_channel_id = null;
            $settings->save();
        }
    }
};
