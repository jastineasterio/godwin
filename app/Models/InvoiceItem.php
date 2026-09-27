<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A single line on an invoice (snapshot of a fee structure). */
class InvoiceItem extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'invoice_id',
        'fee_structure_id',
        'description',
        'quantity',
        'unit_amount',
        'amount',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /** Parent invoice. */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Originating fee definition (nullable for ad-hoc charges). */
    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }
}
