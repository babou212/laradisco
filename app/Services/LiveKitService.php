<?php

namespace App\Services;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\RoomCreateOptions;
use Agence104\LiveKit\RoomServiceClient;
use Agence104\LiveKit\VideoGrant;
use Agence104\LiveKit\WebhookReceiver;
use App\Models\User;
use Livekit\DataPacket\Kind;
use Livekit\ListParticipantsResponse;
use Livekit\WebhookEvent;

class LiveKitService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $url,
        private readonly int $tokenTtl,
        private readonly ?string $apiUrl = null,
    ) {}

    /**
     * Generate an access token for a user to join a specific room.
     */
    public function generateToken(User $user, string $roomName): string
    {
        $tokenOptions = (new AccessTokenOptions)
            ->setIdentity((string) $user->id)
            ->setName($user->display_name ?? $user->username)
            ->setTtl($this->tokenTtl);

        $videoGrant = (new VideoGrant)
            ->setRoomJoin()
            ->setRoomName($roomName)
            ->setCanPublish()
            ->setCanSubscribe();

        return (new AccessToken($this->apiKey, $this->apiSecret))
            ->init($tokenOptions)
            ->setGrant($videoGrant)
            ->toJwt();
    }

    /**
     * Create a new LiveKit room.
     */
    public function createRoom(string $roomName, int $maxParticipants = 50, int $emptyTimeout = 300): mixed
    {
        $svc = $this->getRoomServiceClient();

        $opts = (new RoomCreateOptions)
            ->setName($roomName)
            ->setEmptyTimeout($emptyTimeout)
            ->setMaxParticipants($maxParticipants);

        return $svc->createRoom($opts);
    }

    /**
     * List participants in a room.
     */
    public function listParticipants(string $roomName): ListParticipantsResponse
    {
        $svc = $this->getRoomServiceClient();

        return $svc->listParticipants($roomName);
    }

    /**
     * Remove a participant from a room.
     */
    public function removeParticipant(string $roomName, string $identity): void
    {
        $svc = $this->getRoomServiceClient();

        $svc->removeParticipant($roomName, $identity);
    }

    /**
     * Delete a room.
     */
    public function deleteRoom(string $roomName): void
    {
        $svc = $this->getRoomServiceClient();

        $svc->deleteRoom($roomName);
    }

    /**
     * Send a data packet to participants in a room.
     *
     * With no destination identities the packet is delivered to everyone
     * currently in the room — the mechanism the soundboard uses to tell exactly
     * the members of a voice channel to play a clip. Reliable delivery is used
     * so the trigger is not dropped.
     *
     * @param  list<string>  $destinationIdentities
     */
    public function sendData(string $roomName, string $payload, array $destinationIdentities = [], ?string $topic = null): void
    {
        $svc = $this->getRoomServiceClient();

        $svc->sendData($roomName, $payload, Kind::RELIABLE, $destinationIdentities, $topic);
    }

    /**
     * Get the LiveKit server URL for client-side connections.
     */
    public function getServerUrl(): string
    {
        return $this->url;
    }

    /**
     * Verify and decode an incoming LiveKit webhook payload.
     *
     * Validates the signed JWT in the Authorization header and the SHA256
     * checksum of the raw body before returning the decoded event. Throws if
     * the signature or checksum does not match our API credentials.
     */
    public function receiveWebhook(string $body, ?string $authHeader): WebhookEvent
    {
        return (new WebhookReceiver($this->apiKey, $this->apiSecret))
            ->receive($body, $authHeader);
    }

    private function getRoomServiceClient(): RoomServiceClient
    {
        // Prefer the explicit server-side API host when configured (the backend
        // may reach LiveKit at a different address than clients do); otherwise
        // derive it from the client URL by swapping the ws(s) scheme for http(s).
        $httpUrl = $this->apiUrl !== null && $this->apiUrl !== ''
            ? $this->apiUrl
            : str_replace(['ws://', 'wss://'], ['http://', 'https://'], $this->url);

        return new RoomServiceClient($httpUrl, $this->apiKey, $this->apiSecret);
    }
}
