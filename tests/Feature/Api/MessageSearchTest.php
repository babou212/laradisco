<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class MessageSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_matching_messages_in_channel(): void
    {
        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(true);
            $mock->shouldReceive('userCanInChannel')->andReturn(true);
        });

        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'the quick brown fox jumps',
        ]);
        Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'lazy dog naps in the sun',
        ]);
        Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'another brown bag of chips',
        ]);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.search', $channel).'?q=brown'
        );

        $response->assertOk();
        $contents = collect($response->json('data'))->pluck('attributes.content')->all();
        $this->assertEqualsCanonicalizing(
            ['the quick brown fox jumps', 'another brown bag of chips'],
            $contents,
        );
    }

    public function test_search_results_are_scoped_to_target_channel(): void
    {
        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(true);
            $mock->shouldReceive('userCanInChannel')->andReturn(true);
        });

        $user = User::factory()->create();
        $channelA = Channel::factory()->create();
        $channelB = Channel::factory()->create();

        Message::factory()->create([
            'channel_id' => $channelA->id,
            'user_id' => $user->id,
            'content' => 'shared keyword',
        ]);
        Message::factory()->create([
            'channel_id' => $channelB->id,
            'user_id' => $user->id,
            'content' => 'shared keyword too',
        ]);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.search', $channelA).'?q=shared'
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('shared keyword', $response->json('data.0.attributes.content'));
    }

    public function test_search_forbidden_when_user_cannot_view_channel(): void
    {
        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(false);
            $mock->shouldReceive('userCanInChannel')->andReturn(false);
        });

        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.search', $channel).'?q=anything'
        );

        $response->assertForbidden();
    }

    public function test_search_requires_query_param(): void
    {
        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(true);
            $mock->shouldReceive('userCanInChannel')->andReturn(true);
        });

        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.search', $channel)
        );

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/q');
    }

    public function test_search_unauthenticated_returns_401(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->getJson(
            route('api.channels.messages.search', $channel).'?q=hello'
        );

        $response->assertUnauthorized();
    }

    public function test_search_includes_full_author_and_reactions_when_requested(): void
    {
        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(true);
            $mock->shouldReceive('userCanInChannel')->andReturn(true);
        });

        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $message = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'sidenote about otters',
        ]);
        MessageReaction::factory()->create([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '🦦',
        ]);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.search', $channel).'?q=otters&include=user,reactions'
        );

        $response->assertOk();

        $included = collect($response->json('included'));
        $author = $included->firstWhere('type', 'users');
        $this->assertNotNull($author, 'author should be sideloaded');
        $this->assertSame($user->username, $author['attributes']['display_name']);
        $this->assertNotNull($author['attributes']['created_at'], 'full user load should include created_at');

        $this->assertNotNull($included->firstWhere('type', 'reactions'), 'reaction should be sideloaded');
    }
}
