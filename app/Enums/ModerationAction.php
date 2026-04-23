<?php

namespace App\Enums;

enum ModerationAction: string
{
    case Ban = 'ban';
    case Unban = 'unban';
    case Jail = 'jail';
    case Unjail = 'unjail';
    case MessageDelete = 'message_delete';
    case ThreadMessageDelete = 'thread_message_delete';
    case RoleAssign = 'role_assign';
    case RoleRemove = 'role_remove';
    case RoleCreate = 'role_create';
    case RoleUpdate = 'role_update';
    case RoleDelete = 'role_delete';
    case ChannelCreate = 'channel_create';
    case ChannelUpdate = 'channel_update';
    case ChannelDelete = 'channel_delete';
    case ChannelOverrideUpdate = 'channel_override_update';
    case ChannelOverrideDelete = 'channel_override_delete';

    public function label(): string
    {
        return match ($this) {
            self::Ban => 'Banned',
            self::Unban => 'Unbanned',
            self::Jail => 'Jailed',
            self::Unjail => 'Unjailed',
            self::MessageDelete => 'Deleted Message',
            self::ThreadMessageDelete => 'Deleted Thread Reply',
            self::RoleAssign => 'Assigned Role',
            self::RoleRemove => 'Removed Role',
            self::RoleCreate => 'Created Role',
            self::RoleUpdate => 'Updated Role',
            self::RoleDelete => 'Deleted Role',
            self::ChannelCreate => 'Created Channel',
            self::ChannelUpdate => 'Updated Channel',
            self::ChannelDelete => 'Deleted Channel',
            self::ChannelOverrideUpdate => 'Updated Channel Override',
            self::ChannelOverrideDelete => 'Deleted Channel Override',
        };
    }
}
