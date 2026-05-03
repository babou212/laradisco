<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class ChannelAttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_member_can_upload_channel_attachment(): void
    {
        $this->fakeS3();
        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(true);
        });

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 50, 'application/pdf');

        $response = $this->actingAs($user)->postJson(
            route('api.channels.attachments.upload', $channel),
            ['file' => $file],
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['attachment_id', 'file_name', 'mime_type', 'size'],
        ]);

        $uuid = $response->json('data.attachment_id');
        $this->assertNotEmpty($uuid);

        $media = $user->refresh()->getMedia('pending_attachments')->first();
        $this->assertNotNull($media);
        $this->assertSame($uuid, $media->uuid);
        Storage::disk('s3')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_user_without_channel_access_is_forbidden(): void
    {
        $this->fakeS3();
        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(false);
        });

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $file = UploadedFile::fake()->image('photo.png');

        $response = $this->actingAs($user)->postJson(
            route('api.channels.attachments.upload', $channel),
            ['file' => $file],
        );

        $response->assertForbidden();
    }

    public function test_guest_cannot_upload_channel_attachment(): void
    {
        $channel = Channel::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 50);

        $response = $this->postJson(
            route('api.channels.attachments.upload', $channel),
            ['file' => $file],
        );

        $response->assertUnauthorized();
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $this->fakeS3();
        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(true);
        });

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $file = UploadedFile::fake()->create('huge.bin', 200 * 1024); // 200 MB > 100 MB limit

        $response = $this->actingAs($user)->postJson(
            route('api.channels.attachments.upload', $channel),
            ['file' => $file],
        );

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/file');
    }
}
