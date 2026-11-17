<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SMS delivery event — records each status change for a message.
 */
class SmsDeliveryEvent extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'sms_message_id',
        'status',
        'provider_status',
        'occurred_at',
        'error_message',
        'raw_data',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    public function smsMessage(): BelongsTo
    {
        return $this->belongsTo(SmsMessage::class);
    }
}
