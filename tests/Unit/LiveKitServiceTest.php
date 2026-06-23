<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\LiveKitService;
use Mockery;
use PHPUnit\Framework\TestCase;

class LiveKitServiceTest extends TestCase
{
    private LiveKitService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LiveKitService(
            apiKey: 'test-api-key',
            apiSecret: 'test-api-secret-that-is-long-enough-for-hs256-signing',
            url: 'ws://localhost:7880',
            tokenTtl: 21600,
        );
    }

    public function test_generate_token_returns_jwt_string(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user->shouldReceive('getAttribute')->with('display_name')->andReturn('Test User');
        $user->shouldReceive('getAttribute')->with('username')->andReturn('testuser');

        $token = $this->service->generateToken($user, 'test-room');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // JWT tokens have 3 parts separated by dots
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function test_generate_token_grants_metadata_and_data_permissions(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user->shouldReceive('getAttribute')->with('display_name')->andReturn('Test User');
        $user->shouldReceive('getAttribute')->with('username')->andReturn('testuser');

        $token = $this->service->generateToken($user, 'test-room');

        $payload = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
        $video = $payload['video'] ?? [];

        // setAttributes() (mute-state broadcast) needs canUpdateOwnMetadata.
        $this->assertTrue($video['canUpdateOwnMetadata'] ?? false);
        // publishData() (push-to-talk speaking packets) needs canPublishData.
        $this->assertTrue($video['canPublishData'] ?? false);
    }

    public function test_get_server_url_returns_configured_url(): void
    {
        $this->assertEquals('ws://localhost:7880', $this->service->getServerUrl());
    }

    public function test_get_server_url_with_custom_url(): void
    {
        $service = new LiveKitService(
            apiKey: 'key',
            apiSecret: 'secret',
            url: 'wss://livekit.example.com',
            tokenTtl: 3600,
        );

        $this->assertEquals('wss://livekit.example.com', $service->getServerUrl());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
