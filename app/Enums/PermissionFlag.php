<?php

namespace App\Enums;

enum PermissionFlag: string
{
    case ManageChannels = 'manage_channels';
    case ManageRoles = 'manage_roles';
    case ManageServer = 'manage_server';
    case ViewAuditLog = 'view_audit_log';
    case ManageEmojis = 'manage_emojis';

    case KickMembers = 'kick_members';
    case BanMembers = 'ban_members';
    case InviteMembers = 'invite_members';

    case ViewChannels = 'view_channels';
    case SendMessages = 'send_messages';
    case SendThreadMessages = 'send_thread_messages';
    case CreateThreads = 'create_threads';
    case EmbedLinks = 'embed_links';
    case AttachFiles = 'attach_files';
    case AddReactions = 'add_reactions';
    case MentionEveryone = 'mention_everyone';
    case ManageMessages = 'manage_messages';
    case ManageThreads = 'manage_threads';
    case ReadMessageHistory = 'read_message_history';
    case PinMessages = 'pin_messages';

    case Connect = 'connect';
    case Speak = 'speak';
    case Video = 'video';
    case MuteMembers = 'mute_members';
    case DeafenMembers = 'deafen_members';
    case MoveMembers = 'move_members';

    case Administrator = 'administrator';

    /**
     * Get the human-readable label for this permission.
     */
    public function label(): string
    {
        return match ($this) {
            self::ManageChannels => 'Manage Channels',
            self::ManageRoles => 'Manage Roles',
            self::ManageServer => 'Manage Server',
            self::ViewAuditLog => 'View Audit Log',
            self::ManageEmojis => 'Manage Emojis',
            self::KickMembers => 'Kick Members',
            self::BanMembers => 'Ban Members',
            self::InviteMembers => 'Invite Members',
            self::ViewChannels => 'View Channels',
            self::SendMessages => 'Send Messages',
            self::SendThreadMessages => 'Send Messages in Threads',
            self::CreateThreads => 'Create Threads',
            self::EmbedLinks => 'Embed Links',
            self::AttachFiles => 'Attach Files',
            self::AddReactions => 'Add Reactions',
            self::MentionEveryone => 'Mention @everyone',
            self::ManageMessages => 'Manage Messages',
            self::ManageThreads => 'Manage Threads',
            self::ReadMessageHistory => 'Read Message History',
            self::PinMessages => 'Pin Messages',
            self::Connect => 'Connect',
            self::Speak => 'Speak',
            self::Video => 'Video',
            self::MuteMembers => 'Mute Members',
            self::DeafenMembers => 'Deafen Members',
            self::MoveMembers => 'Move Members',
            self::Administrator => 'Administrator',
        };
    }

    /**
     * Get the default permissions for the @everyone role.
     *
     * @return list<self>
     */
    public static function defaultEveryonePermissions(): array
    {
        return [
            self::ViewChannels,
            self::SendMessages,
            self::SendThreadMessages,
            self::CreateThreads,
            self::EmbedLinks,
            self::AttachFiles,
            self::AddReactions,
            self::ReadMessageHistory,
            self::Connect,
            self::Speak,
            self::Video,
        ];
    }
}
