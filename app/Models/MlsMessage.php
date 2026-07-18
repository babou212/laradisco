<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MlsMessage extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'group_id',
        'sender_user_id',
        'sender_device_id',
        'message_type',
        'message_bytes',
        'epoch',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'epoch' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
