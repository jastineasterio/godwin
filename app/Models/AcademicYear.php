<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Academic year (e.g. 2026) containing one or more terms. */
class AcademicYear extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /** Terms belonging to this year. */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('start_date');
    }

    /** Fee structures scoped to this year. */
    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }

    /** Invoices issued in this year. */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** The active academic year. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }
}
