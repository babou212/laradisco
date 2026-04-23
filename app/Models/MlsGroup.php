<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MlsGroup extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'group_id',
        'creator_user_id',
        'creator_device_id',
        'current_epoch',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'current_epoch' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }
}
