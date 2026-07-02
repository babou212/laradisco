<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\ChannelType;
use App\Events\VoiceChannelJoined;
use App\Events\VoiceChannelLeft;
use App\Events\VoiceChannelMoved;
use App\Http\Controllers\Controller;
use App\Http\Requests\JoinVoiceChannelRequest;
use App\Http\Requests\MoveVoiceMemberRequest;
use App\Models\Channel;
use App\Models\ServerSetting;
use App\Models\User;
use App\Services\LiveKitService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @group Voice
 */
class VoiceChannelController extends Controller
{
    use ApiResponse;

    /** Presence cache TTL (hours), mirroring the LiveKit webhook controller. */
    private const PRESENCE_TTL_HOURS = 6;

    public function __construct(
        private readonly LiveKitService $liveKitService,
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * List voice participants
     *
     * Get the current participants of every voice channel the user can access,
     * keyed by channel id.
     *
     * @response 200 {"data": {"12": [{"user_id": 7, "username": "alice", "muted": false}]}}
     */
    public function participants(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessibleChannels = $this->permissionService->getAccessibleChannels($user);

        $voiceParticipants = [];
        $startedAt = [];

        foreach ($accessibleChannels as $channel) {
            if ($channel->type === ChannelType::Voice) {
                $cached = Cache::get("voice_channel:{$channel->id}:participants", []);

                $voiceParticipants[$channel->id] = array_values(array_map(function (array $participant): array {
                    unset($participant['_sid']);

                    return $participant;
                }, $cached));

                $startedAt[$channel->id] = Cache::get("voice_channel:{$channel->id}:started_at");
            }
        }

        return $this->successResponse([
            'participants' => $voiceParticipants,
            'started_at' => $startedAt,
        ]);
    }

    /**
     * Join a voice channel
     *
     * Returns a LiveKit access token, server URL, room name and the channel's
     * current shared E2EE key + index.
     *
     * @response 200 {"data": {"token": "eyJhbGci...", "url": "wss://voice.example.com", "room": "voice-channel-12", "channel_id": 12, "channel_name": "General Voice", "e2ee_key": "base64key==", "e2ee_key_index": 0}}
     */
    public function join(JoinVoiceChannelRequest $request, Channel $channel): JsonResponse
    {
        $user = $request->user();
        $roomName = "voice-channel-{$channel->id}";

        $token = $this->liveKitService->generateToken($user, $roomName);

        // Current shared E2EE key + index for this room (rotated when members leave).
        $e2ee = $this->liveKitService->currentE2eeKey($channel->id);

        return $this->successResponse([
            'token' => $token,
            'url' => $this->liveKitService->getServerUrl(),
            'room' => $roomName,
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'e2ee_key' => $e2ee['key'],
            'e2ee_key_index' => $e2ee['index'],
            'started_at' => Cache::get("voice_channel:{$channel->id}:started_at"),
        ]);
    }

    /**
     * Get the voice E2EE key
     *
     * Return the current E2EE key + index for a channel.
     *
     * @response 200 {"data": {"e2ee_key": "base64key==", "e2ee_key_index": 0}}
     *
     * Used by clients to resync after a reconnect, in case a rotation broadcast
     * was missed while their websocket was down. Authorization mirrors join
     * (Connect permission on the voice channel), so a user who has left — and
     * lost Connect — cannot fetch the rotated key.
     */
    public function key(JoinVoiceChannelRequest $request, Channel $channel): JsonResponse
    {
        $e2ee = $this->liveKitService->currentE2eeKey($channel->id);

        return $this->successResponse([
            'e2ee_key' => $e2ee['key'],
            'e2ee_key_index' => $e2ee['index'],
        ]);
    }

    /**
     * Leave a voice channel
     *
     * Forcibly removes the authenticated user from the voice room so the leave is
     * reflected immediately.
     *
     * @response 200 {"message": "Left voice channel", "data": {"channel_id": 12}}
     *
     * Presence removal flows through LiveKit's `participant_left` webhook.
     * We forcibly remove the participant from the room so an intentional leave
     * is reflected immediately, rather than waiting on LiveKit's
     * disconnect-detection timeout.
     */
    public function leave(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        try {
            $this->liveKitService->removeParticipant(
                "voice-channel-{$channel->id}",
                (string) $user->id,
            );
        } catch (Throwable $e) {
            Log::debug('LiveKit removeParticipant failed on leave', [
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->successResponse([
            'channel_id' => $channel->id,
        ], 'Left voice channel');
    }

    /**
     * Move a member to another voice channel
     *
     * Forcibly moves another user from this voice channel into a different
     * one. Requires the `move_members` permission (or Administrator).
     *
     * @response 200 {"message": "Moved member", "data": {"from_channel_id": 12, "to_channel_id": 14, "user_id": 7}}
     *
     * The target is removed from this channel's LiveKit room, which flows
     * through the existing `participant_left` webhook to clear presence and
     * broadcast `voice.left`. The moved user's own client is expected to
     * reconnect to the destination channel in response to the `voice.moved`
     * broadcast below, at which point the normal join flow broadcasts
     * `voice.joined` for the destination channel.
     */
    public function move(MoveVoiceMemberRequest $request, Channel $channel): JsonResponse
    {
        $targetUserId = (int) $request->input('user_id');

        /** @var Channel $toChannel */
        $toChannel = Channel::findOrFail($request->input('to_channel_id'));

        $participants = Cache::get("voice_channel:{$channel->id}:participants", []);
        if (! array_key_exists($targetUserId, $participants)) {
            return $this->notFoundResponse('That member is not currently in this voice channel.');
        }

        /** @var User $targetUser */
        $targetUser = User::findOrFail($targetUserId);

        try {
            $this->liveKitService->removeParticipant(
                "voice-channel-{$channel->id}",
                (string) $targetUserId,
            );
        } catch (Throwable $e) {
            Log::debug('LiveKit removeParticipant failed on move', [
                'from_channel_id' => $channel->id,
                'to_channel_id' => $toChannel->id,
                'user_id' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
        }

        VoiceChannelMoved::dispatch($channel, $toChannel, $targetUser);

        return $this->successResponse([
            'from_channel_id' => $channel->id,
            'to_channel_id' => $toChannel->id,
            'user_id' => $targetUserId,
        ], 'Moved member');
    }

    /**
     * Park in the AFK channel
     *
     * Cosmetically place the authenticated user in the server's configured AFK
     * channel. The AFK channel has no LiveKit room; this only writes the shared
     * presence cache and broadcasts a `voice.joined` so every client shows the
     * user parked there. No-op if no AFK channel is configured.
     *
     * @response 200 {"message": "Parked in AFK channel", "data": {"channel_id": 12}}
     */
    public function parkAfk(Request $request): JsonResponse|Response
    {
        $user = $request->user();

        $channel = $this->afkChannel();
        if ($channel === null) {
            return $this->noContentResponse();
        }

        $cacheKey = "voice_channel:{$channel->id}:participants";
        $participants = Cache::get($cacheKey, []);
        $participants[$user->id] = [
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'avatar_urls' => $user->avatar_urls,
        ];
        Cache::put($cacheKey, $participants, now()->addHours(self::PRESENCE_TTL_HOURS));

        VoiceChannelJoined::dispatch($channel, $user);

        return $this->successResponse(['channel_id' => $channel->id], 'Parked in AFK channel');
    }

    /**
     * Leave the AFK channel
     *
     * Remove the authenticated user from the AFK channel's presence cache and
     * broadcast a `voice.left`. No-op if no AFK channel is configured or the
     * user is not currently parked.
     *
     * @response 200 {"message": "Left AFK channel", "data": {"channel_id": 12}}
     */
    public function unparkAfk(Request $request): JsonResponse|Response
    {
        $user = $request->user();

        $channel = $this->afkChannel();
        if ($channel === null) {
            return $this->noContentResponse();
        }

        $cacheKey = "voice_channel:{$channel->id}:participants";
        $participants = Cache::get($cacheKey, []);

        if (! array_key_exists($user->id, $participants)) {
            return $this->noContentResponse();
        }

        unset($participants[$user->id]);

        if (empty($participants)) {
            Cache::forget($cacheKey);
        } else {
            Cache::put($cacheKey, $participants, now()->addHours(self::PRESENCE_TTL_HOURS));
        }

        VoiceChannelLeft::dispatch($channel, $user);

        return $this->successResponse(['channel_id' => $channel->id], 'Left AFK channel');
    }

    /** Resolve the configured AFK channel, or null when unset/missing. */
    private function afkChannel(): ?Channel
    {
        $afkChannelId = ServerSetting::instance()->afk_channel_id;

        if ($afkChannelId === null) {
            return null;
        }

        return Channel::find($afkChannelId);
    }
}
