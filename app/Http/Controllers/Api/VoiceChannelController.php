<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\ChannelType;
use App\Events\VoiceChannelJoined;
use App\Events\VoiceChannelLeft;
use App\Http\Controllers\Controller;
use App\Http\Requests\JoinVoiceChannelRequest;
use App\Models\Channel;
use App\Services\LiveKitService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
                $voiceParticipants[$channel->id] = array_values($cached);
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

        // Track the participant in cache
        $cacheKey = "voice_channel:{$channel->id}:participants";
        $participants = Cache::get($cacheKey, []);
        $participants[$user->id] = [
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'avatar_path' => $user->avatar_path,
        ];
        Cache::put($cacheKey, $participants, now()->addHours(2));

        // Generate or retrieve E2EE shared key for this room
        $e2eeKey = Cache::remember(
            "voice_channel:{$channel->id}:e2ee_key",
            now()->addHours(2),
            fn () => bin2hex(random_bytes(32))
        );

        VoiceChannelJoined::dispatch($channel, $user);

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
     * Leave a voice channel — notifies other participants.
     */
    public function leave(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        $cacheKey = "voice_channel:{$channel->id}:participants";
        $participants = Cache::get($cacheKey, []);
        unset($participants[$user->id]);

        if (empty($participants)) {
            Cache::forget($cacheKey);
            Cache::forget("voice_channel:{$channel->id}:e2ee_key");
        } else {
            Cache::put($cacheKey, $participants, now()->addHours(2));
        }

        VoiceChannelLeft::dispatch($channel, $user);

        return $this->successResponse([
            'channel_id' => $channel->id,
        ], 'Left voice channel');
    }
}
