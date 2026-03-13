<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DmSenderKey extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'dm_group_id',
        'user_id',
        'device_id',
        'distribution_id',
    ];

    /**
     * @return BelongsTo<DirectMessageGroup, $this>
     */
    public function dmGroup(): BelongsTo
    {
        return $this->belongsTo(DirectMessageGroup::class, 'dm_group_id');
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
