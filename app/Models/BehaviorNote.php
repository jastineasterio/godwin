<?php

namespace App\Models;

use App\Enums\NoteType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Character / behavior / spiritual development note for a student.
 * Powers the Senior Pastor's moral-development reports and the
 * parent portal's behaviour feed.
 */
class BehaviorNote extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'student_id',
        'noted_by',
        'type',
        'title',
        'note',
        'is_positive',
        'occurred_on',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => NoteType::class,
            'is_positive' => 'boolean',
            'occurred_on' => 'date',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function notedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'noted_by');
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopeOfType(Builder $query, NoteType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopePositive(Builder $query): Builder
    {
        return $query->where('is_positive', true);
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }
}
