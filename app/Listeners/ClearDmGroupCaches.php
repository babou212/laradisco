<?php

namespace App\Listeners;

use App\Events\DirectMessageSent;

class ClearDmGroupCaches
{
    public function handle(DirectMessageSent $event): void
    {
        $event->message->clearCaches();
    }
}
