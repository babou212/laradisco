<?php

namespace Tests\Feature\Api;

use App\Enums\ChannelType;
use App\Enums\PermissionFlag;
use App\Models\Channel;
use App\Models\Role;
use App\Models\ServerSetting;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerSettingUpdateTest extends TestCase
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

    public function test_afk_channel_can_be_set_to_a_voice_channel(): void
    {
        $user = $this->userWith(PermissionFlag::ManageServer);
        $voice = Channel::factory()->create(['type' => ChannelType::Voice]);

        $this->actingAs($user)
            ->putJson(route('api.settings.server.update'), ['afk_channel_id' => $voice->id])
            ->assertOk()
            ->assertJsonPath('afk_channel_id', $voice->id);

        $this->assertSame($voice->id, ServerSetting::instance()->afk_channel_id);
    }

    public function test_afk_channel_rejects_a_text_channel(): void
    {
        $user = $this->userWith(PermissionFlag::ManageServer);
        $text = Channel::factory()->create(['type' => ChannelType::Text]);

        $this->actingAs($user)
            ->putJson(route('api.settings.server.update'), ['afk_channel_id' => $text->id])
            ->assertStatus(422);
    }

    public function test_update_requires_manage_server_permission(): void
    {
        $voice = Channel::factory()->create(['type' => ChannelType::Voice]);

        $this->actingAs(User::factory()->create())
            ->putJson(route('api.settings.server.update'), ['afk_channel_id' => $voice->id])
            ->assertForbidden();
    }
}
