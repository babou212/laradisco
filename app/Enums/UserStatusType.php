<?php

namespace App\Enums;

enum UserStatusType: string
{
    case Online = 'online';
    case Idle = 'idle';
    case DoNotDisturb = 'dnd';
    case Offline = 'offline';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Idle => 'Idle',
            self::DoNotDisturb => 'Do Not Disturb',
            self::Offline => 'Offline',
        };
    }
}
