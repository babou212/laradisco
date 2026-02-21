<?php

use App\Enums\PermissionFlag;
use App\Models\Channel;
use App\Models\DirectMessageGroup;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// User's private channel for notifications, presence updates
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});

// Channel presence - returns user data for online presence
Broadcast::channel('channel.{channelId}', function (User $user, int $channelId) {
    $channel = Channel::find($channelId);

    if (! $channel || ! $user->hasPermission(PermissionFlag::ViewChannels, $channel)) {
        return false;
    }

    return [
        'id' => $user->id,
        'username' => $user->username,
        'display_name' => $user->display_name,
        'avatar_path' => $user->avatar_path,
        'custom_status' => $user->custom_status,
    ];
});

// Voice channel — private channel for join/leave event broadcasts
Broadcast::channel('voice.channel.{channelId}', function (User $user, int $channelId) {
    $channel = Channel::find($channelId);

    if (! $channel) {
        return false;
    }

    return $user->hasPermission(PermissionFlag::Connect, $channel);
});

// Direct message group presence
Broadcast::channel('direct-message.{groupId}', function (User $user, int $groupId) {
    $group = DirectMessageGroup::find($groupId);

    if (! $group || ! $group->participants->contains($user->id)) {
        return false;
    }

    return [
        'id' => $user->id,
        'username' => $user->username,
        'display_name' => $user->display_name,
        'avatar_path' => $user->avatar_path,
        'custom_status' => $user->custom_status,
    ];
});

// Global online users presence (optional - for member list)
Broadcast::channel('online', function (User $user) {
    return [
        'id' => $user->id,
        'username' => $user->username,
        'display_name' => $user->display_name,
        'avatar_path' => $user->avatar_path,
        'custom_status' => $user->custom_status,
        'status' => $user->status ?? 'online',
    ];
});
