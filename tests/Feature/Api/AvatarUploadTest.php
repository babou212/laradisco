<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_avatar(): void
    {
        $this->fakeS3();

        $user = User::factory()->create();
        $avatar = UploadedFile::fake()->image('avatar.png', 256, 256);

        $response = $this->actingAs($user)->postJson(
            route('api.settings.profile.avatar.upload'),
            ['avatar' => $avatar],
        );

        $response->assertOk();
        $this->assertCount(1, $user->refresh()->getMedia('avatar'));

        $media = $user->getFirstMedia('avatar');
        Storage::disk('s3')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_avatar_urls_point_at_the_stable_avatar_endpoint(): void
    {
        $this->fakeS3();

        $user = User::factory()->create();
        $user->addMedia(UploadedFile::fake()->image('seed.png', 64, 64))
            ->toMediaCollection('avatar');

        $user->refresh();
        $media = $user->getFirstMedia('avatar');
        $urls = $user->avatar_urls;

        $this->assertNotNull($urls);
        $this->assertIsString($urls['original']);
        $this->assertStringContainsString("/users/{$user->id}/avatar/{$media->id}/original", $urls['original']);
    }

    public function test_guest_cannot_upload_avatar(): void
    {
        $avatar = UploadedFile::fake()->image('avatar.png');

        $response = $this->postJson(
            route('api.settings.profile.avatar.upload'),
            ['avatar' => $avatar],
        );

        $response->assertUnauthorized();
    }
}
