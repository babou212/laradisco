<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserKeyBackup extends Model
{
    /**
     * Binary columns that must not be JSON-serialized.
     *
     * @var list<string>
     */
    protected $hidden = [
        'encrypted_bundle',
        'salt',
        'nonce',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'encrypted_bundle',
        'salt',
        'nonce',
        'argon2_params',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'argon2_params' => 'array',
            'version' => 'integer',
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
