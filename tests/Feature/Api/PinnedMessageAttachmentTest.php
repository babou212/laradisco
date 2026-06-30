<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\DirectMessageGroup;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Tests\TestCase;

class PinnedMessageAttachmentTest extends TestCase
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

    public function test_channel_pins_include_attachment_data(): void
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

        $this->actingAs($user)->postJson(
            route('api.channels.messages.pin', [$channel, $messageId]),
        )->assertOk();

        // The include used to be rejected (400 InvalidIncludeQuery); it must now
        // succeed and carry the attachment's file_name through to the client.
        $this->actingAs($user)->getJson(
            route('api.channels.pins.index', $channel).'?include=user,reactions,attachments',
        )
            ->assertOk()
            ->assertJsonFragment(['file_name' => 'photo.png']);
    }

    public function test_dm_pins_include_attachment_data(): void
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

        $send = $this->actingAs($alice)->postJson(
            route('api.direct-messages.messages.store', $group),
            ['content' => 'with file', 'attachment_ids' => [$uuid]],
        )->assertCreated();

        $messageId = (int) $send->json('data.id');

        $this->actingAs($alice)->postJson(
            route('api.direct-messages.messages.pin', [$group, $messageId]),
        )->assertOk();

        $this->actingAs($alice)->getJson(
            route('api.direct-messages.pins.index', $group).'?include=user,reactions,attachments',
        )
            ->assertOk()
            ->assertJsonFragment(['file_name' => 'selfie.png']);
    }
}
