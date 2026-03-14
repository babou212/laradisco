<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevicePrekey extends Model
{
    public $timestamps = false;

    /**
     * Binary columns that must not be JSON-serialized.
     *
     * @var list<string>
     */
    protected $hidden = [
        'public_key',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'user_id',
        'prekey_id',
        'public_key',
        'used',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prekey_id' => 'integer',
            'used' => 'boolean',
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
     * @return BelongsTo<UserDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id', 'device_id');
    }
}
