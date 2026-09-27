<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A payment against an invoice (receipt-generating record).
 * Only `completed` payments count toward revenue.
 */
class Payment extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'receipt_number',
        'invoice_id',
        'student_id',
        'amount',
        'method',
        'reference',
        'paid_at',
        'status',
        'received_by',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Completed);
    }

    /** Payments received between two dates (revenue charts). */
    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('paid_at', [$from, $to]);
    }
}
