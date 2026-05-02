<?php

namespace Tests\Feature\Api;

use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectMessageSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_matching_dm_messages_for_participant(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);

        DirectMessage::factory()->create([
            'direct_message_group_id' => $group->id,
            'user_id' => $alice->id,
            'content' => 'wanna grab coffee',
        ]);
        DirectMessage::factory()->create([
            'direct_message_group_id' => $group->id,
            'user_id' => $bob->id,
            'content' => 'sure, the usual spot',
        ]);

        $response = $this->actingAs($alice)->getJson(
            route('api.direct-messages.messages.search', $group).'?q=coffee'
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('wanna grab coffee', $response->json('data.0.attributes.content'));
    }

    public function test_search_results_scoped_to_target_dm_group(): void
    {
        [$alice, $bob, $carol] = User::factory()->count(3)->create();

        $aliceBob = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $aliceBob->participants()->attach([$alice->id, $bob->id]);

        $aliceCarol = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $aliceCarol->participants()->attach([$alice->id, $carol->id]);

        DirectMessage::factory()->create([
            'direct_message_group_id' => $aliceBob->id,
            'user_id' => $alice->id,
            'content' => 'top secret plan',
        ]);
        DirectMessage::factory()->create([
            'direct_message_group_id' => $aliceCarol->id,
            'user_id' => $alice->id,
            'content' => 'top secret cake recipe',
        ]);

        $response = $this->actingAs($alice)->getJson(
            route('api.direct-messages.messages.search', $aliceBob).'?q=top'
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('top secret plan', $response->json('data.0.attributes.content'));
    }

    public function test_search_forbidden_for_non_participant(): void
    {
        [$alice, $bob, $eve] = User::factory()->count(3)->create();

        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);

        DirectMessage::factory()->create([
            'direct_message_group_id' => $group->id,
            'user_id' => $alice->id,
            'content' => 'private chat',
        ]);

        $response = $this->actingAs($eve)->getJson(
            route('api.direct-messages.messages.search', $group).'?q=private'
        );

        $response->assertForbidden();
    }

    public function test_search_requires_query_param(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);

        $response = $this->actingAs($alice)->getJson(
            route('api.direct-messages.messages.search', $group)
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('q');
    }

    public function test_search_unauthenticated_returns_401(): void
    {
        $group = DirectMessageGroup::factory()->create();

        $response = $this->getJson(
            route('api.direct-messages.messages.search', $group).'?q=hello'
        );

        $response->assertUnauthorized();
    }
}
