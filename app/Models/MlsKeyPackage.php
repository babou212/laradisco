<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MlsKeyPackage extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'device_id',
        'key_package_bytes',
        'key_package_hash',
        'identity_signature',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
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
     * @param  Builder<MlsKeyPackage>  $query
     * @return Builder<MlsKeyPackage>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('consumed_at');
    }
}
