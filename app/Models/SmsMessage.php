<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Individual SMS message record.
 *
 * Tracks provider message ID, status, delivery events, and idempotency
 * keys to prevent duplicate sends.
 */
class SmsMessage extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'campaign_id',
        'user_id',
        'to',
        'message',
        'category',
        'sender_id',
        'provider_message_id',
        'batch_id',
        'provider_type',
        'provider_name',
        'provider_status',
        'status',
        'sent_at',
        'delivered_at',
        'error_message',
        'idempotency_key',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SmsCampaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryEvents(): HasMany
    {
        return $this->hasMany(SmsDeliveryEvent::class);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
