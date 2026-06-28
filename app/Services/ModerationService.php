<?php

namespace App\Services;

use App\Enums\ChannelType;
use App\Events\UserBanned;
use App\Models\Ban;
use App\Models\Channel;
use App\Models\Role;
use App\Models\User;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class ModerationService
{
    public function __construct(
        private readonly LiveKitService $liveKit,
    ) {}

    /**
     * Ban a user from the server.
     *
     * Beyond recording the ban, this enforces it immediately: the banned user is
     * told to log out (broadcast), forcibly disconnected from any voice channel,
     * and has all of their API tokens revoked so every device drops.
     */
    public function ban(User $target, User $actor, ?string $reason = null, ?\DateTimeInterface $expiresAt = null): Ban
    {
        $ban = Ban::create([
            'user_id' => $target->id,
            'banned_by' => $actor->id,
            'reason' => $reason,
            'expires_at' => $expiresAt,
        ]);

        $this->flushUserCaches($target);

        event(new UserBanned($target, $reason, $expiresAt));

        $this->removeFromVoiceChannels($target);

        $target->tokens()->delete();

        return $ban;
    }

    /**
     * Forcibly disconnect a banned user from every voice channel they are in.
     *
     * Removal triggers LiveKit's participant_left webhook, which already handles
     * presence-cache cleanup, E2EE key rotation, and the VoiceChannelLeft
     * broadcast — so we only issue the removal here. Each call is isolated so a
     * LiveKit error on one room does not abort the ban or skip other rooms.
     */
    protected function removeFromVoiceChannels(User $target): void
    {
        $voiceChannels = Channel::where('type', ChannelType::Voice)->get();

        foreach ($voiceChannels as $channel) {
            $participants = Cache::get("voice_channel:{$channel->id}:participants", []);

            if (! array_key_exists($target->id, $participants)) {
                continue;
            }

            try {
                $this->liveKit->removeParticipant(
                    "voice-channel-{$channel->id}",
                    (string) $target->id,
                );
            } catch (Throwable $e) {
                Log::debug('LiveKit removeParticipant failed on ban', [
                    'channel_id' => $channel->id,
                    'user_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Unban a user (delete all active bans).
     */
    public function unban(User $target): void
    {
        $target->bans()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->delete();

        $this->flushUserCaches($target);
    }

    /**
     * Invalidate the target user's cached state after a moderation action.
     *
     * Unban uses a mass delete, which does not fire model events, so the cached
     * ban status (and the rest of the user's permission caches, whose semantics
     * change with a ban) is flushed explicitly here for both ban and unban.
     */
    protected function flushUserCaches(User $target): void
    {
        Cache::tags([CacheKeys::userTag($target->id)])->flush();
    }

    /**
     * Jail a user — assign the Jailed role and remove all other roles.
     */
    public function jail(User $target): void
    {
        $jailedRole = Role::where('name', 'Jailed')->first();

        if (! $jailedRole) {
            return;
        }

        $target->syncRoles([$jailedRole]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Unjail a user — remove Jailed role and assign default everyone role.
     */
    public function unjail(User $target): void
    {
        $jailedRole = Role::where('name', 'Jailed')->first();
        $defaultRole = Role::where('is_default', true)->first();

        if ($jailedRole) {
            $target->removeRole($jailedRole);
        }

        if ($defaultRole && ! $target->hasRole($defaultRole)) {
            $target->assignRole($defaultRole);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
