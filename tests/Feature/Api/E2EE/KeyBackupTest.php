<?php

namespace Tests\Feature\Api\E2EE;

use App\Models\User;
use App\Models\UserKeyBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Tests\TestCase;

class KeyBackupTest extends TestCase
{
    use InteractsWithE2ee;
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'encrypted_bundle' => base64_encode('bundle'),
            'salt' => str_repeat('s', 44),
            'nonce' => str_repeat('n', 16),
            'argon2_params' => ['memory' => 65536, 'iterations' => 3, 'parallelism' => 1],
        ];
    }

    private function seedBackup(User $user): UserKeyBackup
    {
        return UserKeyBackup::create(array_merge($this->payload(), ['user_id' => $user->id, 'version' => 1]));
    }

    public function test_exists_reports_backup_presence(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('api.e2ee.keys.backup.exists'))
            ->assertOk()->assertJsonPath('data.exists', false);

        $this->seedBackup($user);

        $this->actingAs($user)->getJson(route('api.e2ee.keys.backup.exists'))
            ->assertOk()->assertJsonPath('data.exists', true);
    }

    public function test_stores_a_backup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.e2ee.keys.backup.store'), $this->payload())
            ->assertCreated();

        $this->assertDatabaseHas('user_key_backups', ['user_id' => $user->id, 'version' => 1]);
    }

    public function test_store_rejects_a_duplicate(): void
    {
        $user = User::factory()->create();
        $this->seedBackup($user);

        $this->actingAs($user)->postJson(route('api.e2ee.keys.backup.store'), $this->payload())
            ->assertStatus(409);
    }

    public function test_store_validates_argon2_params(): void
    {
        $user = User::factory()->create();
        $bad = $this->payload();
        $bad['argon2_params']['memory'] = 1024; // below min 65536

        $this->actingAs($user)->postJson(route('api.e2ee.keys.backup.store'), $bad)
            ->assertStatus(422);
    }

    public function test_store_validates_salt_length(): void
    {
        $user = User::factory()->create();
        $bad = $this->payload();
        $bad['salt'] = 'short';

        $this->actingAs($user)->postJson(route('api.e2ee.keys.backup.store'), $bad)
            ->assertStatus(422);
    }

    public function test_updates_a_backup_and_bumps_the_version(): void
    {
        $user = User::factory()->create();
        $this->seedBackup($user);

        $this->actingAs($user)->putJson(route('api.e2ee.keys.backup.update'), $this->payload())
            ->assertOk();

        $this->assertDatabaseHas('user_key_backups', ['user_id' => $user->id, 'version' => 2]);
    }

    public function test_update_without_a_backup_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson(route('api.e2ee.keys.backup.update'), $this->payload())
            ->assertNotFound();
    }

    public function test_deletes_a_backup(): void
    {
        $user = User::factory()->create();
        $this->seedBackup($user);

        $this->actingAs($user)->deleteJson(route('api.e2ee.keys.backup.destroy'))
            ->assertOk();

        $this->assertDatabaseMissing('user_key_backups', ['user_id' => $user->id]);
    }

    public function test_show_does_not_count_attempts_but_reported_failures_lock(): void
    {
        $user = User::factory()->create();
        $this->seedBackup($user);

        // Retrieval alone never locks — a multi-device restore may GET many times.
        for ($i = 0; $i < 8; $i++) {
            $this->actingAs($user)->getJson(route('api.e2ee.keys.backup.show'))->assertOk();
        }

        // Client-reported decrypt failures do count; the fifth locks the backup.
        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($user)->postJson(route('api.e2ee.keys.backup.failed'))->assertOk();
        }
        $this->actingAs($user)->postJson(route('api.e2ee.keys.backup.failed'))->assertStatus(423);

        $this->actingAs($user)->getJson(route('api.e2ee.keys.backup.show'))->assertStatus(423);
    }

    public function test_confirm_resets_the_attempt_counter(): void
    {
        $user = User::factory()->create();
        $backup = $this->seedBackup($user);
        $backup->update(['failed_attempt_count' => 4]);

        $this->actingAs($user)->postJson(route('api.e2ee.keys.backup.confirm'))->assertOk();

        $this->assertDatabaseHas('user_key_backups', ['user_id' => $user->id, 'failed_attempt_count' => 0]);
    }

    public function test_unlock_rejects_an_invalid_code(): void
    {
        $user = User::factory()->create();
        $this->seedBackup($user);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.keys.backup.unlock'), ['two_factor_code' => '000000'])
            ->assertStatus(422);
    }

    public function test_unlock_with_a_valid_code_resets_attempts(): void
    {
        $user = User::factory()->create(['two_factor_secret' => encrypt('SECRET')]);
        $backup = $this->seedBackup($user);
        $backup->update(['failed_attempt_count' => 5, 'locked_at' => now()]);

        $this->mock(TwoFactorAuthenticationProvider::class, function ($mock) {
            $mock->shouldReceive('verify')->andReturn(true);
        });

        $this->actingAs($user)
            ->postJson(route('api.e2ee.keys.backup.unlock'), ['two_factor_code' => '123456'])
            ->assertOk();

        $this->assertDatabaseHas('user_key_backups', ['user_id' => $user->id, 'failed_attempt_count' => 0]);
    }
}
