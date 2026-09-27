<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily attendance record — one row per student per date (unique composite).
 * Supports Present / Absent / Late / Excused plus daycare check-in/out times.
 */
class Attendance extends Model
{
    /** Table name is singular `attendance` (one row per student per day). */
    protected $table = 'attendance';

    /** @var array<int, string> */
    protected $fillable = [
        'student_id',
        'date',
        'status',
        'marked_by',
        'check_in_time',
        'check_out_time',
        'remarks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    /** Student this mark belongs to. */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Teacher who marked the roll. */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopeOnDate(Builder $query, $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    /** Attendance rows for one student. */
    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    /** Every mark made in a class on a given date (for the register screen). */
    public function scopeForClassDate(Builder $query, int $classId, string $date): Builder
    {
        return $query->whereDate('date', $date)
            ->whereIn('student_id', function ($sub) use ($classId) {
                $sub->select('id')->from('students')->where('class_id', $classId);
            });
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to);
    }

    public function scopeWithStatus(Builder $query, AttendanceStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
