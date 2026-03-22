<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $created_at
 */
class E2eeAuditLog extends Model
{
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'e2ee_audit_log';

    /**
     * Binary columns that must not be JSON-serialized.
     *
     * @var list<string>
     */
    protected $hidden = [
        'public_key',
        'signature',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event_type',
        'device_id',
        'public_key',
        'signature',
        'metadata',
        'previous_hash',
        'entry_hash',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
