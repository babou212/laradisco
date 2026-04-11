<?php

use App\Enums\PermissionFlag;
use App\Models\Channel;
use App\Models\DirectMessageGroup;
use App\Models\Thread;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});

Broadcast::channel('user.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});

Broadcast::channel('channel.{channelId}', function (User $user, int $channelId) {
    $channel = Channel::find($channelId);

    if (! $channel || ! app(PermissionService::class)->userCanViewChannel($user, $channel)) {
        return false;
    }

    return [
        'id' => $user->id,
        'username' => $user->username,
        'display_name' => $user->display_name,
        'avatar_urls' => $user->avatar_urls,
        'custom_status' => $user->custom_status,
    ];
});

Broadcast::channel('voice.channel.{channelId}', function (User $user, int $channelId) {
    $channel = Channel::find($channelId);

    if (! $channel) {
        return false;
    }

    return app(PermissionService::class)->userCanInChannel($user, $channel, PermissionFlag::Connect);
});

Broadcast::channel('direct-message.{groupId}', function (User $user, int $groupId) {
    $group = DirectMessageGroup::find($groupId);

    if (! $group || ! $group->participants->contains($user->id)) {
        return false;
    }

    return [
        'id' => $user->id,
        'username' => $user->username,
        'display_name' => $user->display_name,
        'avatar_urls' => $user->avatar_urls,
        'custom_status' => $user->custom_status,
    ];
});

Broadcast::channel('thread.{threadId}', function (User $user, int $threadId) {
    $thread = Thread::find($threadId);

    if (! $thread) {
        return false;
    }

    $channel = Channel::find($thread->channel_id);

    if (! $channel || ! app(PermissionService::class)->userCanViewChannel($user, $channel)) {
        return false;
    }

    return [
        'id' => $user->id,
        'username' => $user->username,
        'display_name' => $user->display_name,
        'avatar_urls' => $user->avatar_urls,
        'custom_status' => $user->custom_status,
    ];
});

Broadcast::channel('presence', fn (User $user) => (bool) $user);
