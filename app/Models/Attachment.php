<?php

namespace App\Models;

use App\Enums\AttachmentStatus;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property AttachmentStatus $status
 * @property string|null $storage_path
 * @property string|null $thumbnail_path
 * @property string $file_name
 * @property string $mime_type
 * @property int $size
 * @property int|null $thumbnail_size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $attachable_type
 * @property int|string|null $attachable_id
 */
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'attachable_type',
        'attachable_id',
        'storage_path',
        'file_name',
        'mime_type',
        'size',
        'thumbnail_path',
        'thumbnail_size',
        'width',
        'height',
        'status',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttachmentStatus::class,
            'size' => 'integer',
            'thumbnail_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
