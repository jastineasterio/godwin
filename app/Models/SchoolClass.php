<?php

namespace App\Models;

use App\Enums\ClassLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A school class / room (Daycare, Nursery, KG1, KG2...).
 * Table is named `classes` (reserved word avoided via $table + backticks by Laravel).
 */
class SchoolClass extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'classes';

    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'code',
        'level',
        'teacher_id',
        'capacity',
        'description',
        'is_active',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'level' => ClassLevel::class,
            'is_active' => 'boolean',
            'capacity' => 'integer',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    /** Class teacher (users row with role = teacher). */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Students assigned to this class. */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    /** Subjects allocated to this class (+ allocated teacher via pivot). */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(
            Subject::class,
            'class_subject',
            'class_id',
            'subject_id'
        )->withPivot('teacher_id')->withTimestamps();
    }

    /** Weekly timetable for this class. */
    public function timetableSlots(): HasMany
    {
        return $this->hasMany(Timetable::class, 'class_id');
    }

    /** Assessments taken by this class. */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'class_id');
    }

    /** Homework / activities posted for this class. */
    public function homeworks(): HasMany
    {
        return $this->hasMany(Homework::class, 'class_id');
    }

    /** Fee structures scoped to this class (NULL class_id = all classes). */
    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class, 'class_id');
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfLevel(Builder $query, ClassLevel $level): Builder
    {
        return $query->where('level', $level->value);
    }

    /** Ordered for display: sort_order then name. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
