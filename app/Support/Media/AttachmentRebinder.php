<?php

namespace App\Support\Media;

use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\User;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Image as ImageGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\ResponsiveImages\Jobs\GenerateResponsiveImagesJob;

class AttachmentRebinder
{
    public function __construct(
        private readonly FileManipulator $fileManipulator,
    ) {}

    /**
     * Rebind pending attachment media uploaded by the user to the given owning
     * message. Conversions are dispatched after the rebind because the User
     * model does not declare attachment conversions — they live on the Message
     * / DirectMessage models.
     *
     * @param  array<int, string>  $uuids
     */
    public function rebind(User $user, Message|DirectMessage $owner, array $uuids): void
    {
        if (empty($uuids)) {
            return;
        }

        Media::query()
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->where('collection_name', 'pending_attachments')
            ->whereIn('uuid', $uuids)
            ->update([
                'model_type' => $owner->getMorphClass(),
                'model_id' => $owner->getKey(),
                'collection_name' => 'attachments',
            ]);

        $rebound = Media::query()
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->where('collection_name', 'attachments')
            ->whereIn('uuid', $uuids)
            ->get();

        $imageGenerator = new ImageGenerator;

        foreach ($rebound as $media) {
            $this->fileManipulator->createDerivedFiles(
                $media,
                onlyConversionNames: [],
                onlyMissing: false,
            );

            // Spatie's FileManipulator only regenerates responsive_images that were already
            // seeded at upload-time. Pending media uploaded under the User collection does
            // not seed them, so dispatch the responsive-images job directly here. Guard on
            // image-able mime/extension to avoid wasted work for audio/video/PDF/etc.
            if (! $imageGenerator->canConvert($media)) {
                continue;
            }

            /** @var class-string<GenerateResponsiveImagesJob> $jobClass */
            $jobClass = config(
                'media-library.jobs.generate_responsive_images',
                GenerateResponsiveImagesJob::class,
            );

            $job = new $jobClass($media);
            $job->onConnection(config('media-library.queue_connection_name'));
            $job->onQueue(config('media-library.queue_name'));

            config('media-library.queue_conversions_after_database_commit')
                ? dispatch($job)->afterCommit()
                : dispatch($job);
        }
    }
}
