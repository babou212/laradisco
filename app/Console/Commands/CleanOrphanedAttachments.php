<?php

namespace App\Console\Commands;

use App\Enums\AttachmentStatus;
use App\Models\Attachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOrphanedAttachments extends Command
{
    protected $signature = 'attachments:clean-orphaned';

    protected $description = 'Delete expired pending attachments and their stored files';

    public function handle(): int
    {
        $orphaned = Attachment::where('status', AttachmentStatus::Pending)
            ->where('expires_at', '<', now())
            ->limit(100)
            ->get();

        if ($orphaned->isEmpty()) {
            $this->info('No orphaned attachments to clean up.');

            return self::SUCCESS;
        }

        $disk = Storage::disk('attachments');
        $deleted = 0;

        foreach ($orphaned as $attachment) {
            $paths = array_filter([$attachment->storage_path, $attachment->thumbnail_path]);
            foreach ($paths as $path) {
                $disk->delete($path);
            }
            $attachment->delete();
            $deleted++;
        }

        $this->info("Cleaned up {$deleted} orphaned attachment(s).");

        return self::SUCCESS;
    }
}
