<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    /**
     * Binary columns that must not be JSON-serialized.
     *
     * @var list<string>
     */
    protected $hidden = [
        'device_identity_key',
        'identity_signature',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'device_identity_key',
        'identity_signature',
        'last_seen_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
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
