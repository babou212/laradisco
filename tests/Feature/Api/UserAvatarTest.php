<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    private function seedAvatar(User $user): Media
    {
        $user->addMedia(UploadedFile::fake()->image('seed.png', 256, 256))
            ->toMediaCollection('avatar');

        return $user->refresh()->getFirstMedia('avatar');
    }

    public function test_guest_cannot_stream_an_avatar(): void
    {
        $this->fakeS3();

        $user = User::factory()->create();
        $media = $this->seedAvatar($user);

        $response = $this->getJson(route('api.users.avatar', [
            'user' => $user, 'version' => $media->id, 'size' => 'original',
        ]));

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_stream_the_original_avatar(): void
    {
        $this->fakeS3();

        $viewer = User::factory()->create();
        $user = User::factory()->create();
        $media = $this->seedAvatar($user);

        $response = $this->actingAs($viewer)->get(route('api.users.avatar', [
            'user' => $user, 'version' => $media->id, 'size' => 'original',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_authenticated_user_can_stream_a_conversion(): void
    {
        $this->fakeS3();

        $viewer = User::factory()->create();
        $user = User::factory()->create();
        $media = $this->seedAvatar($user);

        $this->assertTrue($media->hasGeneratedConversion('thumb'));

        $response = $this->actingAs($viewer)->get(route('api.users.avatar', [
            'user' => $user, 'version' => $media->id, 'size' => 'thumb',
        ]));

        $response->assertOk();
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_user_without_an_avatar_returns_404(): void
    {
        $this->fakeS3();

        $viewer = User::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('api.users.avatar', [
            'user' => $user, 'version' => 1, 'size' => 'original',
        ]));

        $response->assertNotFound();
    }

    public function test_invalid_size_is_rejected_by_the_route(): void
    {
        $this->fakeS3();

        $viewer = User::factory()->create();
        $user = User::factory()->create();
        $media = $this->seedAvatar($user);

        $response = $this->actingAs($viewer)->get(
            "/api/v1/users/{$user->id}/avatar/{$media->id}/huge"
        );

        $response->assertNotFound();
    }

    public function test_stale_version_still_streams_the_current_avatar(): void
    {
        $this->fakeS3();

        $viewer = User::factory()->create();
        $user = User::factory()->create();
        $this->seedAvatar($user);

        $response = $this->actingAs($viewer)->get(route('api.users.avatar', [
            'user' => $user, 'version' => 999999, 'size' => 'original',
        ]));

        $response->assertOk();
        $this->assertNotEmpty($response->streamedContent());
    }
}
