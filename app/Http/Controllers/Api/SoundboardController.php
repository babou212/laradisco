<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\ChannelType;
use App\Enums\PermissionFlag;
use App\Http\Controllers\Controller;
use App\Http\Resources\SoundResource;
use App\Models\Channel;
use App\Models\SoundboardSound;
use App\Models\User;
use App\Services\AudioProbe;
use App\Services\LiveKitService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @group Soundboard
 */
class SoundboardController extends Controller
{
    use ApiResponse;

    private const MAX_DURATION_MS = 20_000;

    private const DURATION_TOLERANCE_MS = 500;

    private const MAX_FILE_SIZE_KB = 5_120;

    private const PLAY_COOLDOWN_SECONDS = 1;

    public function __construct(
        private readonly LiveKitService $liveKitService,
        private readonly PermissionService $permissionService,
        private readonly AudioProbe $audioProbe,
    ) {}

    /**
     * List sounds
     *
     * List every sound in the shared soundboard library, newest first.
     *
     * @response 200 {"data": [{"type": "sounds", "id": "1", "attributes": {"name": "airhorn", "duration_ms": 1200, "url": "https://cdn.example.com/sounds/airhorn.ogg", "mime_type": "audio/ogg", "uploaded_by_id": 7, "created_at": "2026-06-30T12:00:00.000000Z"}}]}
     */
    public function index(): JsonResponse
    {
        $sounds = SoundboardSound::query()
            ->with(['media', 'user'])
            ->latest()
            ->get();

        return $this->successResponse(SoundResource::collection($sounds));
    }

    /**
     * Upload a sound
     *
     * Upload a new sound. Any member may contribute. The clip is validated to
     * be audio and at most ~20s server-side, regardless of client trimming.
     *
     * @response 201 {"message": "Sound uploaded", "data": {"type": "sounds", "id": "9", "attributes": {"name": "tada", "duration_ms": 1800, "url": "https://cdn.example.com/sounds/tada.ogg", "mime_type": "audio/ogg", "uploaded_by_id": 7, "created_at": "2026-06-30T12:05:00.000000Z"}}}
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'file' => [
                'required', 'file', 'max:'.self::MAX_FILE_SIZE_KB,
                'mimetypes:audio/ogg,application/ogg,audio/opus,audio/webm,audio/mpeg,audio/mp4,audio/aac,audio/x-aac,audio/wav,audio/x-wav,audio/flac,audio/x-flac',
            ],
        ]);

        $durationMs = $this->audioProbe->durationMs($request->file('file')->getRealPath());

        if ($durationMs === null) {
            return $this->errorResponse('Could not read the audio file.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($durationMs > self::MAX_DURATION_MS + self::DURATION_TOLERANCE_MS) {
            return $this->errorResponse('Sounds must be 20 seconds or shorter.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $sound = SoundboardSound::create([
            'name' => $request->string('name'),
            'user_id' => $request->user()->id,
            'duration_ms' => min($durationMs, self::MAX_DURATION_MS),
        ]);

        $sound->addMediaFromRequest('file')->toMediaCollection('sound');

        $sound->load(['media', 'user']);

        return $this->successResponse(new SoundResource($sound), 'Sound uploaded', Response::HTTP_CREATED);
    }

    /**
     * Delete a sound
     *
     * Delete a sound. Allowed for the uploader or anyone who can manage the server.
     *
     * @response 204
     */
    public function destroy(Request $request, SoundboardSound $sound): Response
    {
        $user = $request->user();

        $canDelete = $sound->user_id === $user->id || $this->canManageSounds($user);

        if (! $canDelete) {
            return $this->forbiddenResponse('You cannot delete this sound.');
        }

        $sound->delete();

        return $this->noContentResponse();
    }

    /**
     * Play a sound
     *
     * Trigger a sound for everyone currently in the voice channel.
     *
     * Delivery is a LiveKit data packet to the room, so only participants of
     * this voice channel receive it. The caller must be able to speak in the
     * channel and actually be connected to it.
     *
     * @response 204
     */
    public function play(Request $request, Channel $channel): Response
    {
        $request->validate([
            'sound_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        if ($channel->type !== ChannelType::Voice) {
            return $this->errorResponse('Not a voice channel.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::Speak)) {
            return $this->forbiddenResponse('You cannot play sounds in this channel.');
        }

        $participants = Cache::get("voice_channel:{$channel->id}:participants", []);

        if (! array_key_exists($user->id, $participants)) {
            return $this->forbiddenResponse('You must be in the voice channel to play a sound.');
        }

        $lock = Cache::lock("soundboard:play:{$channel->id}", self::PLAY_COOLDOWN_SECONDS);

        if (! $lock->get()) {
            return $this->errorResponse('Slow down — a sound is already playing.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $sound = SoundboardSound::with('media')->find($request->integer('sound_id'));
        $media = $sound?->soundMedia();

        if ($sound === null || $media === null) {
            return $this->notFoundResponse('Sound not found');
        }

        $payload = json_encode([
            'type' => 'play_sound',
            'sound_id' => $sound->id,
            'name' => $sound->name,
            'url' => $media->getTemporaryUrl(now()->addMinutes(240)),
            'triggered_by' => (string) $user->id,
        ]);

        try {
            $this->liveKitService->sendData("voice-channel-{$channel->id}", $payload);
        } catch (Throwable $e) {
            return $this->errorResponse('Could not deliver the sound.', Response::HTTP_BAD_GATEWAY);
        }

        return $this->noContentResponse();
    }

    /**
     * Whether the user may manage (delete others') sounds.
     */
    private function canManageSounds(User $user): bool
    {
        return $user->isAdministrator() || $user->hasPermissionTo(PermissionFlag::ManageServer->value);
    }
}
