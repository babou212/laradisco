<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\DirectMessageGroup;
use App\Models\Message;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class MessageLinkPreviewTest extends TestCase
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

    /** @return array<string, mixed> */
    private function samplePreview(): array
    {
        return [
            'url' => 'https://www.reddit.com/r/programming/comments/abc/some_post/',
            'title' => 'Some Post Title',
            'description' => 'A description of the linked content.',
            'site_name' => 'reddit',
            'image_url' => 'https://i.redd.it/example.jpg',
            'image_width' => 1200,
            'image_height' => 630,
            'fetched_at' => 1749700000000,
        ];
    }

    public function test_channel_message_persists_link_preview_and_survives_refetch(): void
    {
        $this->allowChannelAccess();

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $preview = $this->samplePreview();

        $send = $this->actingAs($user)->postJson(
            route('api.channels.messages.store', $channel),
            ['content' => 'check this '.$preview['url'], 'link_preview' => $preview],
        )->assertCreated();

        // Returned immediately in the create response.
        $send->assertJsonPath('data.attributes.link_preview.title', 'Some Post Title');
        $send->assertJsonPath('data.attributes.link_preview.site_name', 'reddit');

        // Persisted to the database as a decoded array.
        $messageId = (int) $send->json('data.id');
        $message = Message::query()->findOrFail($messageId);
        $this->assertSame($preview, $message->link_preview);

        // Survives a fresh fetch (the channel-switch scenario).
        $refetch = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel),
        )->assertOk();

        $refetch->assertJsonPath('data.0.attributes.link_preview.title', 'Some Post Title');
        $refetch->assertJsonPath('data.0.attributes.link_preview.image_width', 1200);
    }

    public function test_direct_message_persists_link_preview_and_survives_refetch(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);
        $preview = $this->samplePreview();

        $this->actingAs($alice)->postJson(
            route('api.direct-messages.messages.store', $group),
            [
                'message_bytes' => base64_encode('mls-ciphertext'),
                'sender_device_id' => (string) Str::uuid(),
                'link_preview' => $preview,
            ],
        )->assertCreated();

        $refetch = $this->actingAs($alice)->getJson(
            route('api.direct-messages.show', $group),
        )->assertOk();

        $refetch->assertJsonPath('data.0.attributes.link_preview.title', 'Some Post Title');
        $refetch->assertJsonPath('data.0.attributes.link_preview.site_name', 'reddit');
    }

    public function test_message_without_link_preview_stores_null(): void
    {
        $this->allowChannelAccess();

        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $send = $this->actingAs($user)->postJson(
            route('api.channels.messages.store', $channel),
            ['content' => 'plain message'],
        )->assertCreated();

        $message = Message::query()->findOrFail((int) $send->json('data.id'));
        $this->assertNull($message->link_preview);
    }

    public function test_link_preview_without_url_is_rejected(): void
    {
        $this->allowChannelAccess();

        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $this->actingAs($user)->postJson(
            route('api.channels.messages.store', $channel),
            ['content' => 'bad preview', 'link_preview' => ['title' => 'no url here']],
        )->assertStatus(422)
            ->assertJsonFragment(['pointer' => '/data/attributes/link_preview.url']);
    }
}
