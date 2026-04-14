<?php

namespace App\Models;

use App\Enums\AttachmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property AttachmentStatus $status
 * @property string|null $storage_path
 * @property string|null $thumbnail_path
 * @property int|null $encrypted_size
 * @property int|null $thumbnail_size
 * @property string|null $attachable_type
 * @property int|string|null $attachable_id
 */
class EncryptedAttachment extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'attachable_type',
        'attachable_id',
        'storage_path',
        'encrypted_size',
        'thumbnail_path',
        'thumbnail_size',
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
            'encrypted_size' => 'integer',
            'thumbnail_size' => 'integer',
            'expires_at' => 'datetime',
        ];
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
