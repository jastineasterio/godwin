<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SMS credit purchase record.
 *
 * Credits are never manually added — only the provider can confirm
 * a successful purchase and add credits to the account.
 */
class SmsPurchase extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'provider_type',
        'provider_name',
        'provider_purchase_id',
        'transaction_ref',
        'quantity_sms',
        'amount',
        'unit_price',
        'provider_status',
        'expires_at',
        'efd_receipt',
        'payer_phone',
        'raw_response',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'expires_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('provider_status', $status);
    }
}
