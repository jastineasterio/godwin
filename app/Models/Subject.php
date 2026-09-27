<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A subject taught across classes (Phonics, Numeracy, Bible Stories...). */
class Subject extends Model
{
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    /** Classes this subject is allocated to (pivot carries the teacher). */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(
            SchoolClass::class,
            'class_subject',
            'subject_id',
            'class_id'
        )->withPivot('teacher_id')->withTimestamps();
    }

    /** Assessments recorded for this subject. */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /** Homework posted for this subject. */
    public function homeworks(): HasMany
    {
        return $this->hasMany(Homework::class);
    }

    /** Timetable slots for this subject. */
    public function timetableSlots(): HasMany
    {
        return $this->hasMany(Timetable::class);
    }
}
