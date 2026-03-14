<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIdentityKey extends Model
{
    /**
     * Binary columns that must not be JSON-serialized.
     *
     * @var list<string>
     */
    protected $hidden = [
        'identity_key',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'identity_key',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
