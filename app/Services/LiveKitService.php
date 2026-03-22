<?php

namespace App\Services;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\RoomCreateOptions;
use Agence104\LiveKit\RoomServiceClient;
use Agence104\LiveKit\VideoGrant;
use App\Models\User;
use Livekit\ListParticipantsResponse;

class LiveKitService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $url,
        private readonly int $tokenTtl
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
     * Get the LiveKit server URL for client-side connections.
     */
    public function getServerUrl(): string
    {
        return $this->url;
    }

    private function getRoomServiceClient(): RoomServiceClient
    {
        $httpUrl = str_replace(['ws://', 'wss://'], ['http://', 'https://'], $this->url);

        return new RoomServiceClient($httpUrl, $this->apiKey, $this->apiSecret);
    }
}
