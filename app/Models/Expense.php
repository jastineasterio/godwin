<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** School operating expense (revenue vs expense analytics). */
class Expense extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'title',
        'category',
        'amount',
        'incurred_on',
        'reference',
        'receipt_path',
        'description',
        'recorded_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'amount' => 'decimal:2',
            'incurred_on' => 'date',
        ];
    }

    /** User who recorded the expense. */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopeOfType(Builder $query, ExpenseCategory $category): Builder
    {
        return $query->where('category', $category->value);
    }

    /** Expenses within a date range (monthly trend charts). */
    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('incurred_on', '>=', $from)
            ->whereDate('incurred_on', '<=', $to);
    }
}
