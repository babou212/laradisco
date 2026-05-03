<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CleanOrphanedAttachments extends Command
{
    protected $signature = 'attachments:clean-orphaned';

    protected $description = 'Delete expired pending attachment media and their stored files';

    public function handle(): int
    {
        $candidates = Media::query()
            ->where('collection_name', 'pending_attachments')
            ->limit(500)
            ->get();

        $now = now();
        $deleted = 0;

        foreach ($candidates as $media) {
            $expiresAt = $media->getCustomProperty('expires_at');

            if (! is_string($expiresAt)) {
                continue;
            }

            if (Carbon::parse($expiresAt)->greaterThan($now)) {
                continue;
            }

            $media->delete();
            $deleted++;
        }

        if ($deleted === 0) {
            $this->info('No orphaned attachments to clean up.');

            return self::SUCCESS;
        }

        $this->info("Cleaned up {$deleted} orphaned attachment(s).");

        return self::SUCCESS;
    }
}
