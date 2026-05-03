<?php

namespace Tests\Feature\Api;

use App\Models\DirectMessageGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DmAttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_dm_participant_can_upload_attachment(): void
    {
        $this->fakeS3();

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);

        $file = UploadedFile::fake()->image('selfie.png', 256, 256);

        $response = $this->actingAs($alice)->postJson(
            route('api.direct-messages.attachments.upload', $group),
            ['file' => $file],
        );

        $response->assertOk();
        $this->assertCount(1, $alice->refresh()->getMedia('pending_attachments'));
    }

    public function test_non_participant_is_forbidden_from_uploading_to_dm(): void
    {
        $this->fakeS3();

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $stranger = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);

        $file = UploadedFile::fake()->image('selfie.png');

        $response = $this->actingAs($stranger)->postJson(
            route('api.direct-messages.attachments.upload', $group),
            ['file' => $file],
        );

        $response->assertForbidden();
    }
}
