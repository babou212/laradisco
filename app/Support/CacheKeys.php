<?php

namespace App\Support;

class CacheKeys
{
    // TTL constants (in seconds)
    public const TTL_HOT = 120;       // 2 minutes — messages, notifications

    public const TTL_WARM = 300;      // 5 minutes — sidebar, thread details, e2ee

    public const TTL_COLD = 900;      // 15 minutes — profiles, roles, pins

    public const TTL_LONG = 1800;     // 30 minutes — permissions

    public static function channelMessages(int $channelId): string
    {
        return "channel.{$channelId}.messages.latest";
    }

    public static function channelPinnedMessages(int $channelId): string
    {
        return "channel.{$channelId}.pinned_messages";
    }

    public static function userDmGroups(int $userId): string
    {
        return "user.{$userId}.dm_groups";
    }

    public static function dmGroupMessages(int $dmGroupId): string
    {
        return "dm_group.{$dmGroupId}.messages.latest";
    }

    public static function dmGroupPinnedMessages(int $dmGroupId): string
    {
        return "dm_group.{$dmGroupId}.pinned_messages";
    }

    public static function threadDetails(int $threadId): string
    {
        return "thread.{$threadId}.details";
    }

    public static function threadMessages(int $threadId): string
    {
        return "thread.{$threadId}.messages.latest";
    }

    public static function userSidebarCategories(int $userId): string
    {
        return "user.{$userId}.sidebar.categories";
    }

    public static function userProfile(int $userId): string
    {
        return "user.{$userId}.profile";
    }

    public static function userUnreadNotificationCount(int $userId): string
    {
        return "user.{$userId}.unread_notification_count";
    }

    public static function userChannelPermissions(int $userId, int $channelId): string
    {
        return "user.{$userId}.channel.{$channelId}.permissions";
    }

    public static function userAccessibleChannels(int $userId): string
    {
        return "user.{$userId}.accessible_channels";
    }

    public static function channelViewers(int $channelId): string
    {
        return "channel.{$channelId}.viewers";
    }

    public static function rolesListWithCounts(): string
    {
        return 'roles.list_with_counts';
    }

    public static function e2eeAuditLatest(int $userId): string
    {
        return "e2ee.user.{$userId}.audit_latest";
    }

    public static function e2eeKeyPackageCount(string $deviceId): string
    {
        return "e2ee.device.{$deviceId}.key_package_count";
    }

    public static function e2eeMlsGroups(int $userId): string
    {
        return "e2ee.user.{$userId}.mls_groups";
    }

    public static function channelDetails(int $userId, int $channelId): string
    {
        return "user.{$userId}.channel.{$channelId}.details";
    }

    public static function channelTag(int $channelId): string
    {
        return "channel:{$channelId}";
    }

    public static function channelMessagesTag(int $channelId): string
    {
        return "channel:{$channelId}:messages";
    }

    public static function dmGroupMessagesTag(int $dmGroupId): string
    {
        return "dm_group:{$dmGroupId}:messages";
    }

    public static function threadMessagesTag(int $threadId): string
    {
        return "thread:{$threadId}:messages";
    }

    public static function userTag(int $userId): string
    {
        return "user:{$userId}";
    }

    public static function dmGroupTag(int $dmGroupId): string
    {
        return "dm_group:{$dmGroupId}";
    }

    public static function threadTag(int $threadId): string
    {
        return "thread:{$threadId}";
    }

    public const TAG_SIDEBAR = 'sidebar';

    public const TAG_ROLES = 'roles';
}
