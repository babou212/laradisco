<?php

namespace App\Services;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\ServerSetting;

class AfkChannelService
{
    /** Slug of the dedicated AFK channel. */
    public const SLUG = 'afk';

    /**
     * Idempotently ensure the dedicated AFK voice channel exists and is wired up
     * as the server's AFK channel.
     *
     * The channel is deliberately category-less so it never renders inside the
     * normal category sidebar — the client pins it to the bottom on its own. It
     * has no LiveKit room; it exists purely to cosmetically park idle users.
     */
    public static function ensure(): Channel
    {
        $channel = Channel::firstOrCreate(
            ['category_id' => null, 'slug' => self::SLUG],
            [
                'name' => 'afk',
                'type' => ChannelType::Voice,
                'is_private' => false,
                'position' => 0,
            ],
        );

        $settings = ServerSetting::instance();
        if ($settings->afk_channel_id !== $channel->id) {
            $settings->afk_channel_id = $channel->id;
            $settings->save();
        }

        return $channel;
    }
}
