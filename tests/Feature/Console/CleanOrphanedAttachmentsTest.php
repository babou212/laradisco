<?php

namespace Tests\Feature\Console;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class CleanOrphanedAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_pending_media_is_deleted(): void
    {
        $this->fakeS3();

        $user = User::factory()->create();

        $expired = $user->addMedia(File::image('expired.png', 16, 16)->getPathname())
            ->withCustomProperties(['expires_at' => now()->subHour()->toIso8601String()])
            ->toMediaCollection('pending_attachments');

        $fresh = $user->addMedia(File::image('fresh.png', 16, 16)->getPathname())
            ->withCustomProperties(['expires_at' => now()->addHour()->toIso8601String()])
            ->toMediaCollection('pending_attachments');

        $message = Message::factory()->create();
        $bound = $message->addMedia(File::image('bound.png', 16, 16)->getPathname())
            ->toMediaCollection('attachments');

        $this->artisan('attachments:clean-orphaned')->assertSuccessful();

        $this->assertNull(Media::query()->find($expired->id));
        $this->assertNotNull(Media::query()->find($fresh->id));
        $this->assertNotNull(Media::query()->find($bound->id));
    }

    public function test_pending_media_without_expires_at_is_not_deleted(): void
    {
        $this->fakeS3();

        $user = User::factory()->create();
        $media = $user->addMedia(File::image('no-expiry.png', 16, 16)->getPathname())
            ->toMediaCollection('pending_attachments');

        $this->artisan('attachments:clean-orphaned')->assertSuccessful();

        $this->assertNotNull(Media::query()->find($media->id));
    }
}
