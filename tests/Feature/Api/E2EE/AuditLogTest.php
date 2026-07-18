<?php

namespace Tests\Feature\Api\E2EE;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use InteractsWithE2ee;
    use RefreshDatabase;

    public function test_registering_identity_and_device_writes_audit_entries(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.e2ee.identity.register'), ['identity_key' => str_repeat('k', 44)])->assertCreated();
        $this->actingAs($user)->postJson(route('api.e2ee.devices.register'), ['device_id' => $this->uuid(), 'device_name' => 'D'])->assertCreated();

        $response = $this->actingAs($user)
            ->getJson(route('api.e2ee.auditLog.index', $user))
            ->assertOk();

        $events = collect($response->json('data'))->pluck('event_type');
        $this->assertTrue($events->contains('identity_key_registered'));
        $this->assertTrue($events->contains('device_registered'));
    }

    public function test_audit_entries_form_a_hash_chain(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('api.e2ee.identity.register'), ['identity_key' => str_repeat('k', 44)])->assertCreated();
        $this->actingAs($user)->postJson(route('api.e2ee.devices.register'), ['device_id' => $this->uuid()])->assertCreated();

        $data = $this->actingAs($user)->getJson(route('api.e2ee.auditLog.index', $user))->json('data');

        $this->assertNull($data[0]['previous_hash']);
        $this->assertSame($data[0]['entry_hash'], $data[1]['previous_hash']);
    }

    public function test_latest_hash_reflects_the_newest_entry(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('api.e2ee.auditLog.latestHash', $user))
            ->assertOk()->assertJsonPath('data.entry_count', 0)->assertJsonPath('data.latest_hash', null);

        $this->actingAs($user)->postJson(route('api.e2ee.identity.register'), ['identity_key' => str_repeat('k', 44)])->assertCreated();

        $this->actingAs($user)->getJson(route('api.e2ee.auditLog.latestHash', $user))
            ->assertOk()->assertJsonPath('data.entry_count', 1);
    }

    public function test_cannot_read_another_users_audit_log(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.e2ee.auditLog.index', $other))
            ->assertForbidden();
    }
}
