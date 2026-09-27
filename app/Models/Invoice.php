<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Invoice issued to a student (Accountant module).
 * `amount_paid` is kept in sync by the payment service;
 * `balance` is the computed outstanding amount.
 */
class Invoice extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'invoice_number',
        'student_id',
        'academic_year_id',
        'term_id',
        'issue_date',
        'due_date',
        'subtotal',
        'discount',
        'total_amount',
        'amount_paid',
        'status',
        'notes',
        'issued_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Accessors */
    /* ---------------------------------------------------------------------- */

    /** Outstanding balance = total - amount already paid (never negative). */
    public function getBalanceAttribute(): float
    {
        return round(max(0, (float) $this->total_amount - (float) $this->amount_paid), 2);
    }

    /** Collection progress 0–100% (used by the fee collection progress bar). */
    public function getProgressAttribute(): float
    {
        $total = (float) $this->total_amount;

        return $total > 0
            ? round(min(100, ((float) $this->amount_paid / $total) * 100), 1)
            : 0.0;
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** Line items (tuition, feeding, transport...). */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** Payments applied to this invoice. */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (InvoiceStatus $s) => $s->value,
            array_filter(
                InvoiceStatus::cases(),
                fn (InvoiceStatus $s) => $s->isOutstanding()
            )
        ));
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereDate('due_date', '<', now()->toDateString())
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value]);
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }
}
