<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\DirectMessageGroup;
use App\Models\Message;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class MessageWithAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function allowChannelAccess(): void
    {
        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(true);
            $mock->shouldReceive('userCanInChannel')->andReturn(true);
            $mock->shouldReceive('getUsersWithChannelAccess')->andReturn([]);
        });
    }

    public function test_pending_attachment_is_rebound_to_channel_message(): void
    {
        $this->fakeS3();
        $this->allowChannelAccess();

        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $upload = $this->actingAs($user)->postJson(
            route('api.channels.attachments.upload', $channel),
            ['file' => UploadedFile::fake()->image('photo.png', 64, 64)],
        )->assertOk();

        $uuid = $upload->json('data.attachment_id');

        $send = $this->actingAs($user)->postJson(
            route('api.channels.messages.store', $channel),
            ['content' => 'with file', 'attachment_ids' => [$uuid]],
        )->assertCreated();

        $messageId = (int) $send->json('data.id');
        $message = Message::query()->findOrFail($messageId);

        $media = Media::query()->where('uuid', $uuid)->first();
        $this->assertNotNull($media);
        $this->assertSame(Message::class, $media->model_type);
        $this->assertSame((string) $message->id, (string) $media->model_id);
        $this->assertSame('attachments', $media->collection_name);

        $this->assertCount(1, $message->attachments);

        $media->refresh();
        $this->assertNotEmpty($media->responsive_images, 'Responsive images should be generated for image attachments.');
    }

    public function test_pending_attachment_is_rebound_to_direct_message(): void
    {
        $this->fakeS3();

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);

        $upload = $this->actingAs($alice)->postJson(
            route('api.direct-messages.attachments.upload', $group),
            ['file' => UploadedFile::fake()->image('selfie.png', 64, 64)],
        )->assertOk();

        $uuid = $upload->json('data.attachment_id');

        $this->actingAs($alice)->postJson(
            route('api.direct-messages.messages.store', $group),
            ['content' => 'with file', 'attachment_ids' => [$uuid]],
        )->assertCreated();

        $media = Media::query()->where('uuid', $uuid)->first();
        $this->assertNotNull($media);
        $this->assertSame('attachments', $media->collection_name);
    }

    public function test_audio_upload_streams_via_presigned_url_without_thumbnail(): void
    {
        $this->fakeS3();
        $this->allowChannelAccess();

        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $upload = $this->actingAs($user)->postJson(
            route('api.channels.attachments.upload', $channel),
            ['file' => UploadedFile::fake()->create('clip.mp3', 200, 'audio/mpeg')],
        )->assertOk();

        $uuid = $upload->json('data.attachment_id');

        $this->actingAs($user)->postJson(
            route('api.channels.messages.store', $channel),
            ['content' => 'song', 'attachment_ids' => [$uuid]],
        )->assertCreated();

        $media = Media::query()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame('audio/mpeg', $media->mime_type);
        $this->assertFalse($media->hasGeneratedConversion('thumb'));
        $this->assertEmpty($media->responsive_images);
    }

    public function test_other_users_pending_media_cannot_be_attached(): void
    {
        $this->fakeS3();
        $this->allowChannelAccess();

        $user = User::factory()->create();
        $attacker = User::factory()->create();
        $channel = Channel::factory()->create();

        $upload = $this->actingAs($user)->postJson(
            route('api.channels.attachments.upload', $channel),
            ['file' => UploadedFile::fake()->image('photo.png')],
        )->assertOk();

        $uuid = $upload->json('data.attachment_id');

        $this->actingAs($attacker)->postJson(
            route('api.channels.messages.store', $channel),
            ['content' => 'theft attempt', 'attachment_ids' => [$uuid]],
        )->assertCreated();

        $media = Media::query()->where('uuid', $uuid)->first();
        $this->assertNotNull($media);
        $this->assertSame(User::class, $media->model_type);
        $this->assertSame((string) $user->id, (string) $media->model_id);
        $this->assertSame('pending_attachments', $media->collection_name);
    }
}
