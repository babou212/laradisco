<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_api_requests_carry_the_global_throttle(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('api.inbox.index'));

        $response->assertOk();
        // Proves the `throttle:api` group middleware is wired: without it there is
        // no rate-limit header at all and every endpoint is unthrottled.
        $response->assertHeader('X-RateLimit-Limit', '200');
    }

    public function test_exceeding_the_global_limit_returns_429(): void
    {
        $user = User::factory()->create();

        // 200 requests per minute per user; the 201st must be rejected.
        for ($i = 0; $i < 200; $i++) {
            $this->actingAs($user)->getJson(route('api.inbox.index'))->assertOk();
        }

        $this->actingAs($user)
            ->getJson(route('api.inbox.index'))
            ->assertStatus(429);
    }
}
