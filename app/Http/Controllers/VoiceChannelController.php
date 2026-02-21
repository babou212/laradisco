<?php

namespace App\Http\Controllers;

use App\Events\VoiceChannelJoined;
use App\Events\VoiceChannelLeft;
use App\Http\Requests\JoinVoiceChannelRequest;
use App\Models\Channel;
use App\Services\LiveKitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VoiceChannelController extends Controller
{
    public function __construct(
        private readonly LiveKitService $liveKitService
    ) {}

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
        Cache::put($cacheKey, $participants);

        VoiceChannelJoined::dispatch($channel, $user);

        return response()->json([
            'token' => $token,
            'url' => $this->liveKitService->getServerUrl(),
            'room' => $roomName,
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
        ]);
    }

    /**
     * Leave a voice channel — notifies other participants.
     */
    public function leave(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        // Remove the participant from cache
        $cacheKey = "voice_channel:{$channel->id}:participants";
        $participants = Cache::get($cacheKey, []);
        unset($participants[$user->id]);
        Cache::put($cacheKey, $participants);

        VoiceChannelLeft::dispatch($channel, $user);

        return response()->json([
            'channel_id' => $channel->id,
        ]);
    }
}
