<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SMS campaign — bulk SMS sending job.
 */
class SmsCampaign extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'name',
        'message',
        'category',
        'sender_id',
        'provider_type',
        'provider_name',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'failed_count',
        'status',
        'sent_at',
        'completed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }
}
