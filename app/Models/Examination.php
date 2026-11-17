<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An examination created by the Head of School.
 * Groups multiple subjects and classes; student results live in `assessments`
 * linked via `assessments.examination_id`.
 */
class Examination extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'academic_year_id',
        'term_id',
        'status',
        'description',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** User (Head of School) who created the examination. */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Participating classes. */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(
            SchoolClass::class,
            'examination_classes',
            'examination_id',
            'class_id'
        )->withTimestamps();
    }

    /** Configured subjects with max marks. */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(
            Subject::class,
            'examination_subjects',
            'examination_id',
            'subject_id'
        )->withPivot('max_score')->withTimestamps();
    }

    /** Student result records linked to this examination. */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'examination_id');
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Valid examination lifecycle transitions:
     *   draft  → published   (Head of School publishes)
     *   published → closed   (Head of School closes)
     *
     * Teachers can never change examination status.
     */
    public function transitionableTo(string $to): bool
    {
        return match ($this->status) {
            'draft' => $to === 'published',
            'published' => $to === 'closed',
            default => false,
        };
    }
}
