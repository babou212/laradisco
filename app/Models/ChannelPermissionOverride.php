<?php

namespace App\Models;

use Database\Factories\ChannelPermissionOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<string> $allow
 * @property list<string> $deny
 */
class ChannelPermissionOverride extends Model
{
    /** @use HasFactory<ChannelPermissionOverrideFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'channel_id',
        'role_id',
        'user_id',
        'allow',
        'deny',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow' => 'array',
            'deny' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
