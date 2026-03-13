<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'signed_prekey',
        'signed_prekey_signature',
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
        'signed_prekey',
        'signed_prekey_id',
        'signed_prekey_signature',
        'signed_prekey_timestamp',
        'last_seen_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signed_prekey_id' => 'integer',
            'signed_prekey_timestamp' => 'datetime',
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

    /**
     * @return HasMany<DevicePrekey, $this>
     */
    public function prekeys(): HasMany
    {
        return $this->hasMany(DevicePrekey::class, 'device_id', 'device_id');
    }

    /**
     * Get unused one-time pre-keys for this device.
     *
     * @return HasMany<DevicePrekey, $this>
     */
    public function unusedPrekeys(): HasMany
    {
        return $this->prekeys()->where('used', false);
    }

    /**
     * @return HasMany<ChannelSenderKey, $this>
     */
    public function senderKeys(): HasMany
    {
        return $this->hasMany(ChannelSenderKey::class, 'device_id', 'device_id');
    }
}
