<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A term / semester inside an academic year (e.g. "First Term"). */
class Term extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'academic_year_id',
        'name',
        'start_date',
        'end_date',
        'is_current',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'academic_year_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /** Parent academic year. */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** Assessments recorded in this term. */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /** Invoices issued for this term. */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** The term currently in progress. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }
}
