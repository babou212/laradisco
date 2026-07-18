<?php

namespace Tests\Feature\Api\E2EE;

use App\Models\MlsKeyPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeyPackageTest extends TestCase
{
    use InteractsWithE2ee;
    use RefreshDatabase;

    public function test_uploads_key_packages(): void
    {
        $user = User::factory()->create();
        $device = $this->registerDevice($user);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.keyPackages.upload'), [
                'device_id' => $device->device_id,
                'key_packages' => [
                    ['key_package_bytes' => base64_encode('a'), 'key_package_hash' => $this->kpHash()],
                    ['key_package_bytes' => base64_encode('b'), 'key_package_hash' => $this->kpHash()],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.uploaded', 2);
    }

    public function test_upload_requires_a_device_id(): void
    {
        $user = User::factory()->create();
        $this->registerDevice($user);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.keyPackages.upload'), [
                'key_packages' => [['key_package_bytes' => base64_encode('a'), 'key_package_hash' => $this->kpHash()]],
            ])
            ->assertStatus(422);
    }

    public function test_upload_rejects_an_inactive_or_foreign_device(): void
    {
        $user = User::factory()->create();
        $this->registerDevice($user, null, false);
        $foreign = $this->registerDevice(User::factory()->create());

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.keyPackages.upload'), [
                'device_id' => $foreign->device_id,
                'key_packages' => [['key_package_bytes' => base64_encode('a'), 'key_package_hash' => $this->kpHash()]],
            ])
            ->assertForbidden();
    }

    public function test_upload_validates_hash_length(): void
    {
        $user = User::factory()->create();
        $device = $this->registerDevice($user);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.keyPackages.upload'), [
                'device_id' => $device->device_id,
                'key_packages' => [['key_package_bytes' => base64_encode('a'), 'key_package_hash' => 'short']],
            ])
            ->assertStatus(422);
    }

    public function test_counts_unconsumed_packages_for_a_device(): void
    {
        $user = User::factory()->create();
        $device = $this->registerDevice($user);
        $this->addKeyPackage($user, $device->device_id);
        $this->addKeyPackage($user, $device->device_id);
        MlsKeyPackage::where('user_id', $user->id)->first()->update(['consumed_at' => now()]);

        $this->actingAs($user)
            ->getJson(route('api.e2ee.mls.keyPackages.count', ['device_id' => $device->device_id]))
            ->assertOk()
            ->assertJsonPath('data.count', 1);
    }

    public function test_fetch_consumes_one_package_per_active_device(): void
    {
        $owner = User::factory()->create();
        $device = $this->registerDevice($owner);
        $kp = $this->addKeyPackage($owner, $device->device_id);
        $this->addKeyPackage($owner, $device->device_id);

        $fetcher = User::factory()->create();

        $this->actingAs($fetcher)
            ->getJson(route('api.e2ee.mls.keyPackages.fetch', $owner))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device_id', $device->device_id);

        // exactly one package is now consumed
        $this->assertSame(1, MlsKeyPackage::where('device_id', $device->device_id)->whereNotNull('consumed_at')->count());
    }

    public function test_identity_signature_is_stored_and_returned(): void
    {
        $owner = User::factory()->create();
        $device = $this->registerDevice($owner);
        $sig = base64_encode('an-identity-signature');
        $hash = $this->kpHash();

        $this->actingAs($owner)
            ->postJson(route('api.e2ee.mls.keyPackages.upload'), [
                'device_id' => $device->device_id,
                'key_packages' => [
                    ['key_package_bytes' => base64_encode('kp'), 'key_package_hash' => $hash, 'identity_signature' => $sig],
                ],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('mls_key_packages', ['key_package_hash' => $hash, 'identity_signature' => $sig]);

        $fetcher = User::factory()->create();
        $this->actingAs($fetcher)
            ->getJson(route('api.e2ee.mls.keyPackages.fetch', $owner))
            ->assertOk()
            ->assertJsonPath('data.0.identity_signature', $sig);
    }

    public function test_last_key_package_is_a_reusable_last_resort(): void
    {
        $owner = User::factory()->create();
        $device = $this->registerDevice($owner);
        $this->addKeyPackage($owner, $device->device_id); // exactly one
        $fetcher = User::factory()->create();

        $this->actingAs($fetcher)->getJson(route('api.e2ee.mls.keyPackages.fetch', $owner))
            ->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(1, MlsKeyPackage::where('device_id', $device->device_id)->whereNull('consumed_at')->count());

        $this->actingAs($fetcher)->getJson(route('api.e2ee.mls.keyPackages.fetch', $owner))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_fetch_returns_empty_when_no_packages_available(): void
    {
        $owner = User::factory()->create();
        $this->registerDevice($owner); // device but no key packages
        $fetcher = User::factory()->create();

        $this->actingAs($fetcher)
            ->getJson(route('api.e2ee.mls.keyPackages.fetch', $owner))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
