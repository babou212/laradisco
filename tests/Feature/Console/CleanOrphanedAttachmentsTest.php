<?php

namespace Tests\Feature\Console;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class CleanOrphanedAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_pending_media_is_deleted(): void
    {
        $this->fakeS3();

        $user = User::factory()->create();

        $expired = $user->addMedia(UploadedFile::fake()->image('expired.png', 16, 16))
            ->withCustomProperties(['expires_at' => now()->subHour()->toIso8601String()])
            ->toMediaCollection('pending_attachments');

        $fresh = $user->addMedia(UploadedFile::fake()->image('fresh.png', 16, 16))
            ->withCustomProperties(['expires_at' => now()->addHour()->toIso8601String()])
            ->toMediaCollection('pending_attachments');

        $message = Message::factory()->create();
        $bound = $message->addMedia(UploadedFile::fake()->image('bound.png', 16, 16))
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
        $media = $user->addMedia(UploadedFile::fake()->image('no-expiry.png', 16, 16))
            ->toMediaCollection('pending_attachments');

        $this->artisan('attachments:clean-orphaned')->assertSuccessful();

        $this->assertNotNull(Media::query()->find($media->id));
    }
}
