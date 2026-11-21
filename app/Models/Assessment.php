<?php

namespace App\Models;

use App\Enums\AssessmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Assessment / exam marks (homework, tests, exams, milestones...).
 * `percentage` is a computed accessor used by all performance charts.
 */
class Assessment extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'student_id',
        'class_id',
        'subject_id',
        'term_id',
        'examination_id',
        'status',
        'title',
        'type',
        'score',
        'max_score',
        'remarks',
        'recorded_by',
        'obtained_at',
        'submitted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => AssessmentType::class,
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'obtained_at' => 'date',
            'status' => 'string',
            'submitted_at' => 'datetime',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Accessors */
    /* ---------------------------------------------------------------------- */

    /** Score as a percentage (0–100) — safe against division by zero. */
    public function getPercentageAttribute(): ?float
    {
        $max = (float) $this->max_score;

        return $max > 0 ? round(((float) $this->score / $max) * 100, 1) : null;
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** User (teacher) who entered the marks. */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Examination this result belongs to (NULL for non-exam assessments). */
    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopeForClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeOfType(Builder $query, AssessmentType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /** "Pass / Needs Support" boundary used by Head of School charts. */
    public function scopePassing(Builder $query, float $threshold = 50): Builder
    {
        return $query->whereRaw('score / NULLIF(max_score, 0) * 100 >= ?', [$threshold]);
    }

    /** Results linked to a specific examination. */
    public function scopeForExamination(Builder $query, int $examinationId): Builder
    {
        return $query->where('examination_id', $examinationId);
    }

    /** Results by submission status. */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /** Examination results only (excludes standalone assessments). */
    public function scopeExaminationResults(Builder $query): Builder
    {
        return $query->whereNotNull('examination_id');
    }
}
