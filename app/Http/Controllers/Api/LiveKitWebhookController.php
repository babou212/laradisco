<?php

namespace App\Http\Controllers\Api;

use App\Events\VoiceChannelJoined;
use App\Events\VoiceChannelLeft;
use App\Events\VoiceKeyRotated;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\User;
use App\Services\LiveKitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livekit\ParticipantInfo;
use Throwable;

/**
 * Receives LiveKit server webhooks and reconciles voice channel presence.
 *
 * LiveKit is the single source of truth for who is in a voice channel: it
 * emits `participant_joined` once a participant's media connection is active
 * and `participant_left` when they disconnect — including ungraceful exits
 * (app crash, force quit, network drop), which it detects on its own after a
 * short timeout. The Redis presence cache is therefore only ever written from
 * these webhooks, so it cannot drift into "ghost" participants the way
 * client-driven join/leave calls could.
 */
class LiveKitWebhookController extends Controller
{
    /**
     * How long a presence entry survives without a refreshing webhook. This is
     * a backstop only — `participant_left`/`room_finished` clear entries
     * normally. Matches the access token TTL (max session length).
     */
    private const PRESENCE_TTL_HOURS = 6;

    /**
     * How long a channel's call-start timestamp survives. Unlike the presence
     * TTL above, this isn't a "ghost data" backstop — the key is always
     * cleared explicitly once the channel empties (see onParticipantLeft/
     * onRoomFinished), so this only needs to outlive the longest realistic
     * call, and is refreshed on every join/leave so it doesn't expire out
     * from under a long-running one.
     */
    private const STARTED_AT_TTL_DAYS = 30;

    public function __construct(
        private readonly LiveKitService $liveKitService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            $event = $this->liveKitService->receiveWebhook(
                $request->getContent(),
                $request->header('Authorization'),
            );
        } catch (Throwable $e) {
            Log::warning('Rejected LiveKit webhook', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $room = $event->getRoom();
        $channelId = $room ? $this->channelIdFromRoom($room->getName()) : null;

        match ($event->getEvent()) {
            'participant_joined' => $this->onParticipantJoined($channelId, $event->getParticipant()),
            'participant_left',
            'participant_connection_aborted' => $this->onParticipantLeft($channelId, $event->getParticipant()),
            'room_finished' => $this->onRoomFinished($channelId),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    /**
     * Add the participant to the channel's presence cache and notify clients.
     */
    private function onParticipantJoined(?int $channelId, ?ParticipantInfo $participant): void
    {
        if ($channelId === null || $participant === null) {
            return;
        }

        $user = User::find($participant->getIdentity());
        $channel = Channel::find($channelId);

        if (! $user || ! $channel) {
            return;
        }

        $cacheKey = "voice_channel:{$channelId}:participants";
        $participants = Cache::get($cacheKey, []);
        $participants[$user->id] = [
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'avatar_urls' => $user->avatar_urls,

            '_sid' => $participant->getSid(),
        ];
        Cache::put($cacheKey, $participants, now()->addHours(self::PRESENCE_TTL_HOURS));

        $startedAtKey = "voice_channel:{$channelId}:started_at";
        $startedAtTtl = now()->addDays(self::STARTED_AT_TTL_DAYS);
        Cache::add($startedAtKey, now()->timestamp, $startedAtTtl);
        $startedAt = Cache::get($startedAtKey);
        Cache::put($startedAtKey, $startedAt, $startedAtTtl); // refresh TTL without changing the value

        VoiceChannelJoined::dispatch($channel, $user, $startedAt);
    }

    /**
     * Remove the participant from the channel's presence cache and notify
     * clients, unless this leave belongs to a session that has already been
     * replaced by a newer connection with the same identity.
     */
    private function onParticipantLeft(?int $channelId, ?ParticipantInfo $participant): void
    {
        if ($channelId === null || $participant === null) {
            return;
        }

        $user = User::find($participant->getIdentity());
        $channel = Channel::find($channelId);

        if (! $user || ! $channel) {
            return;
        }

        $cacheKey = "voice_channel:{$channelId}:participants";
        $participants = Cache::get($cacheKey, []);

        $existing = $participants[$user->id] ?? null;

        if ($existing !== null && ($existing['_sid'] ?? null) !== $participant->getSid()) {
            return;
        }

        unset($participants[$user->id]);

        $startedAtKey = "voice_channel:{$channelId}:started_at";

        if (empty($participants)) {
            Cache::forget($cacheKey);
            Cache::forget($startedAtKey);
            $this->liveKitService->forgetE2eeKey($channelId);
            VoiceChannelLeft::dispatch($channel, $user);

            return;
        }

        Cache::put($cacheKey, $participants, now()->addHours(self::PRESENCE_TTL_HOURS));

        $startedAt = Cache::get($startedAtKey);
        if ($startedAt !== null) {
            Cache::put($startedAtKey, $startedAt, now()->addDays(self::STARTED_AT_TTL_DAYS));
        }

        VoiceChannelLeft::dispatch($channel, $user, $startedAt);

        // Rotate the shared E2EE key so the member who just left can no longer
        // decrypt subsequent audio, and broadcast the new key to those remaining.
        // The leaver has lost Connect permission, so they won't receive it.
        $rotated = $this->liveKitService->rotateE2eeKey($channelId);
        VoiceKeyRotated::dispatch($channel, $rotated['key'], $rotated['index']);
    }

    /**
     * Clear all presence for a finished room. A backstop for any
     * `participant_left` events that were missed before the room emptied.
     */
    private function onRoomFinished(?int $channelId): void
    {
        if ($channelId === null) {
            return;
        }

        $cacheKey = "voice_channel:{$channelId}:participants";
        $participants = Cache::get($cacheKey, []);

        Cache::forget($cacheKey);
        Cache::forget("voice_channel:{$channelId}:started_at");
        $this->liveKitService->forgetE2eeKey($channelId);

        $channel = Channel::find($channelId);

        if (! $channel) {
            return;
        }

        foreach (array_keys($participants) as $userId) {
            $user = User::find($userId);

            if ($user) {
                VoiceChannelLeft::dispatch($channel, $user);
            }
        }
    }

    /**
     * Extract the channel id from a "voice-channel-{id}" room name.
     */
    private function channelIdFromRoom(string $roomName): ?int
    {
        if (preg_match('/^voice-channel-(\d+)$/', $roomName, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
