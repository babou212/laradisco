<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\JoinVoiceChannelRequest;
use App\Models\Channel;
use App\Services\LiveKitService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @group Voice
 */
class VoiceChannelController extends Controller
{
    use ApiResponse;

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

        foreach ($accessibleChannels as $channel) {
            if ($channel->type === ChannelType::Voice) {
                $cached = Cache::get("voice_channel:{$channel->id}:participants", []);

                $voiceParticipants[$channel->id] = array_values(array_map(function (array $participant): array {
                    unset($participant['_sid']);

                    return $participant;
                }, $cached));
            }
        }

        return $this->successResponse($voiceParticipants);
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
}
