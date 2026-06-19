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

class VoiceChannelController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly LiveKitService $liveKitService,
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Get all voice channel participants for channels the user can access.
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
     * Join a voice channel — returns a LiveKit token and server URL.
     */
    public function join(JoinVoiceChannelRequest $request, Channel $channel): JsonResponse
    {
        $user = $request->user();
        $roomName = "voice-channel-{$channel->id}";

        $token = $this->liveKitService->generateToken($user, $roomName);

        // Generate or retrieve E2EE shared key for this room
        $e2eeKey = Cache::remember(
            "voice_channel:{$channel->id}:e2ee_key",
            now()->addHours(6),
            fn () => bin2hex(random_bytes(32))
        );

        return $this->successResponse([
            'token' => $token,
            'url' => $this->liveKitService->getServerUrl(),
            'room' => $roomName,
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'e2ee_key' => $e2eeKey,
        ]);
    }

    /**
     * Leave a voice channel.
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
