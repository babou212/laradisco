<?php

namespace App\Models;

use Database\Factories\SoundboardSoundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A clip in the shared soundboard library. The audio file lives on the `s3`
 * disk via a single-file `sound` media collection; presence/playback are
 * handled by LiveKit, not this model.
 */
class SoundboardSound extends Model implements HasMedia
{
    /** @use HasFactory<SoundboardSoundFactory> */
    use HasFactory, InteractsWithMedia;

    protected $table = 'sounds';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'user_id',
        'duration_ms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
        ];
    }

    /**
     * The user who uploaded the sound (null if that account was deleted).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('sound')
            ->singleFile()
            ->useDisk('s3');
    }

    /**
     * The clip's media record, if present.
     */
    public function soundMedia(): ?Media
    {
        return $this->getFirstMedia('sound');
    }
}
