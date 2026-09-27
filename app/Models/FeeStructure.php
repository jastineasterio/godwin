<?php

namespace App\Models;

use App\Enums\FeeFrequency;
use App\Enums\FeeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fee structure (Accountant): Tuition, Transport, Feeding, Daycare...
 * class_id NULL = applies to ALL classes.
 */
class FeeStructure extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'type',
        'class_id',
        'academic_year_id',
        'amount',
        'frequency',
        'description',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => FeeType::class,
            'frequency' => FeeFrequency::class,
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    /** NULL means the fee applies to every class. */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** Invoice line items generated from this fee. */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, FeeType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /** Fees that apply to a specific class (its own fees + universal fees). */
    public function scopeForClass(Builder $query, int $classId): Builder
    {
        return $query->where(function (Builder $q) use ($classId) {
            $q->where('class_id', $classId)->orWhereNull('class_id');
        });
    }
}
