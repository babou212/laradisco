<?php

namespace Tests\Feature\Api;

use App\Enums\ChannelType;
use App\Enums\PermissionFlag;
use App\Models\Channel;
use App\Models\Role;
use App\Models\SoundboardSound;
use App\Models\User;
use App\Services\AudioProbe;
use App\Services\LiveKitService;
use App\Services\PermissionService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

class SoundboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->fakeS3();
    }

    /**
     * Stub the duration probe so tests don't depend on real audio content.
     */
    private function fakeDuration(?int $ms): void
    {
        $this->mock(AudioProbe::class, function (MockInterface $mock) use ($ms): void {
            $mock->shouldReceive('durationMs')->andReturn($ms);
        });
    }

    private function userWith(PermissionFlag $flag): User
    {
        $role = Role::factory()->create(['permissions' => [$flag->value]]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function soundWithMedia(?User $owner = null): SoundboardSound
    {
        $sound = SoundboardSound::factory()->create([
            'user_id' => ($owner ?? User::factory()->create())->id,
        ]);
        $sound->addMedia(UploadedFile::fake()->create('clip.ogg', 20, 'audio/ogg'))
            ->toMediaCollection('sound');

        return $sound;
    }

    // --- Upload ---------------------------------------------------------

    public function test_guest_cannot_upload_a_sound(): void
    {
        $this->postJson(route('api.soundboard.sounds.store'), [
            'name' => 'Airhorn',
            'file' => UploadedFile::fake()->create('clip.ogg', 20, 'audio/ogg'),
        ])->assertUnauthorized();
    }

    public function test_member_can_upload_a_sound(): void
    {
        $this->fakeS3();
        $this->fakeDuration(5000);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.soundboard.sounds.store'), [
            'name' => 'Airhorn',
            'file' => UploadedFile::fake()->create('clip.ogg', 20, 'audio/ogg'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.attributes.name', 'Airhorn');
        $response->assertJsonPath('data.attributes.duration_ms', 5000);

        $this->assertDatabaseHas('sounds', [
            'name' => 'Airhorn',
            'user_id' => $user->id,
            'duration_ms' => 5000,
        ]);

        $sound = SoundboardSound::first();
        $this->assertNotNull($sound->soundMedia());
    }

    public function test_upload_is_rejected_when_longer_than_ten_seconds(): void
    {
        $this->fakeS3();
        $this->fakeDuration(12_000);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.soundboard.sounds.store'), [
            'name' => 'Too long',
            'file' => UploadedFile::fake()->create('clip.ogg', 20, 'audio/ogg'),
        ])->assertUnprocessable();

        $this->assertDatabaseCount('sounds', 0);
    }

    public function test_upload_is_rejected_when_audio_is_unreadable(): void
    {
        $this->fakeS3();
        $this->fakeDuration(null);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.soundboard.sounds.store'), [
            'name' => 'Broken',
            'file' => UploadedFile::fake()->create('clip.ogg', 20, 'audio/ogg'),
        ])->assertUnprocessable();
    }

    // --- Listing --------------------------------------------------------

    public function test_member_can_list_sounds(): void
    {
        $this->fakeS3();
        $this->soundWithMedia();

        $response = $this->actingAs(User::factory()->create())
            ->getJson(route('api.soundboard.sounds.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    // --- Delete ---------------------------------------------------------

    public function test_uploader_can_delete_their_sound(): void
    {
        $this->fakeS3();
        $uploader = User::factory()->create();
        $sound = $this->soundWithMedia($uploader);

        $this->actingAs($uploader)
            ->deleteJson(route('api.soundboard.sounds.destroy', $sound))
            ->assertNoContent();

        $this->assertDatabaseMissing('sounds', ['id' => $sound->id]);
    }

    public function test_manage_server_user_can_delete_any_sound(): void
    {
        $this->fakeS3();
        $admin = $this->userWith(PermissionFlag::ManageServer);
        $sound = $this->soundWithMedia();

        $this->actingAs($admin)
            ->deleteJson(route('api.soundboard.sounds.destroy', $sound))
            ->assertNoContent();

        $this->assertDatabaseMissing('sounds', ['id' => $sound->id]);
    }

    public function test_other_member_cannot_delete_sound(): void
    {
        $this->fakeS3();
        $sound = $this->soundWithMedia();

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('api.soundboard.sounds.destroy', $sound))
            ->assertForbidden();

        $this->assertDatabaseHas('sounds', ['id' => $sound->id]);
    }

    // --- Play -----------------------------------------------------------

    private function voiceChannel(): Channel
    {
        return Channel::factory()->create(['type' => ChannelType::Voice]);
    }

    public function test_member_in_channel_can_play_a_sound(): void
    {
        $this->fakeS3();
        $user = User::factory()->create();
        $channel = $this->voiceChannel();
        $sound = $this->soundWithMedia();

        Cache::put("voice_channel:{$channel->id}:participants", [
            $user->id => ['id' => $user->id],
        ], now()->addHour());

        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanInChannel')->andReturn(true);
        });
        $this->mock(LiveKitService::class, function (MockInterface $mock) use ($channel): void {
            $mock->shouldReceive('sendData')
                ->once()
                ->with("voice-channel-{$channel->id}", \Mockery::type('string'));
        });

        $this->actingAs($user)
            ->postJson(route('api.channels.voice.soundboard.play', $channel), ['sound_id' => $sound->id])
            ->assertNoContent();
    }

    public function test_play_is_forbidden_without_speak_permission(): void
    {
        $user = User::factory()->create();
        $channel = $this->voiceChannel();
        $sound = $this->soundWithMedia();

        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanInChannel')->andReturn(false);
        });

        $this->actingAs($user)
            ->postJson(route('api.channels.voice.soundboard.play', $channel), ['sound_id' => $sound->id])
            ->assertForbidden();
    }

    public function test_play_is_forbidden_when_not_in_the_channel(): void
    {
        $user = User::factory()->create();
        $channel = $this->voiceChannel();
        $sound = $this->soundWithMedia();

        // Speak allowed, but user is not present in the channel's participant cache.
        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanInChannel')->andReturn(true);
        });

        $this->actingAs($user)
            ->postJson(route('api.channels.voice.soundboard.play', $channel), ['sound_id' => $sound->id])
            ->assertForbidden();
    }
}
