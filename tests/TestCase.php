<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fake the `s3` disk and register a deterministic temporary URL builder so
     * presigned URL helpers (e.g. Spatie's `getTemporaryUrl`) work without a
     * real S3 backend in tests.
     */
    protected function fakeS3(string $disk = 's3'): void
    {
        Storage::fake($disk);

        Storage::disk($disk)->buildTemporaryUrlsUsing(
            fn (string $path, $expiration, array $options = []) => 'https://fake-'.$disk.'.test/'.ltrim($path, '/'),
        );
    }
}
