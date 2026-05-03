<?php

namespace App\Support\Media;

use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\User;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

        foreach ($rebound as $media) {
            $this->fileManipulator->createDerivedFiles(
                $media,
                onlyConversionNames: [],
                onlyMissing: false,
                withResponsiveImages: true,
            );
        }
    }
}
