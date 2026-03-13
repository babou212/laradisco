<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DmSenderKeyDistribution extends Model
{
    protected $table = 'dm_sender_key_distributions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dm_group_id',
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
     * @return BelongsTo<DirectMessageGroup, $this>
     */
    public function dmGroup(): BelongsTo
    {
        return $this->belongsTo(DirectMessageGroup::class, 'dm_group_id');
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
