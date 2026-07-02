<?php

namespace Tests\Feature\Api;

use App\Enums\ChannelType;
use App\Enums\PermissionFlag;
use App\Models\Channel;
use App\Models\Role;
use App\Models\ServerSetting;
use App\Models\User;
use App\Services\AfkChannelService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AfkChannelProvisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    private function userWith(PermissionFlag $flag): User
    {
        $role = Role::factory()->create(['permissions' => [$flag->value]]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_ensure_creates_dedicated_afk_channel_and_wires_setting(): void
    {
        $channel = AfkChannelService::ensure();

        $this->assertSame('afk', $channel->slug);
        $this->assertSame('afk', $channel->name);
        $this->assertSame(ChannelType::Voice, $channel->type);
        $this->assertNull($channel->category_id);
        $this->assertFalse($channel->is_private);
        $this->assertSame($channel->id, ServerSetting::instance()->afk_channel_id);
    }

    public function test_ensure_is_idempotent(): void
    {
        $a = AfkChannelService::ensure();
        $b = AfkChannelService::ensure();

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Channel::where('slug', 'afk')->whereNull('category_id')->count());
    }

    public function test_ensure_overrides_a_stale_arbitrary_selection(): void
    {
        $other = Channel::factory()->create(['type' => ChannelType::Voice]);
        $settings = ServerSetting::instance();
        $settings->afk_channel_id = $other->id;
        $settings->save();

        $channel = AfkChannelService::ensure();

        $this->assertSame('afk', $channel->slug);
        $this->assertNotSame($other->id, $channel->id);
        $this->assertSame($channel->id, ServerSetting::instance()->afk_channel_id);
    }

    public function test_afk_channel_cannot_be_deleted(): void
    {
        $afk = AfkChannelService::ensure();
        $user = $this->userWith(PermissionFlag::ManageChannels);

        $this->actingAs($user)
            ->deleteJson(route('api.settings.channels.destroy', $afk))
            ->assertForbidden();

        $this->assertDatabaseHas('channels', ['id' => $afk->id]);
    }

    public function test_a_normal_channel_can_still_be_deleted(): void
    {
        AfkChannelService::ensure();
        $channel = Channel::factory()->create(['type' => ChannelType::Voice]);
        $user = $this->userWith(PermissionFlag::ManageChannels);

        $this->actingAs($user)
            ->deleteJson(route('api.settings.channels.destroy', $channel))
            ->assertNoContent();

        $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
    }
}
