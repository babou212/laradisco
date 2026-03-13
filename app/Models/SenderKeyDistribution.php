<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenderKeyDistribution extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'channel_id',
        'sender_user_id',
        'sender_device_id',
        'distribution_id',
        'recipient_user_id',
        'recipient_device_id',
        'encrypted_distribution',
        'ephemeral_public_key',
        'nonce',
    ];

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
