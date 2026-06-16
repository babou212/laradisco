<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxMessage extends Model
{
    /**
     * Rows are insert/delete only — only created_at is meaningful.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'message_type',
        'message_id',
        'payload',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
            'message_id' => 'integer',
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
