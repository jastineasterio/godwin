<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A student is a PURE DATABASE RECORD — never a login account.
 *
 * Linked to parents/guardians via the `parent_student` pivot, which powers
 * the multi-child parent portal (switch child without re-login).
 */
class Student extends Model
{
    use HasFactory, SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'reg_no',
        'first_name',
        'last_name',
        'other_name',
        'gender',
        'dob',
        'class_id',
        'photo_path',
        'blood_group',
        'medical_notes',
        'admission_date',
        'previous_school',
        'status',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'dob' => 'date',
            'admission_date' => 'date',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Accessors */
    /* ---------------------------------------------------------------------- */

    /** "Amina Juma" */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** Age in whole years, calculated from date of birth. */
    public function getAgeAttribute(): ?int
    {
        return $this->dob?->age;
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    /** Class the student is assigned to. */
    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Parents / guardians linked to this child (MULTI-CHILD PORTAL).
     * Usage: $student->parents()->wherePivot('is_primary_contact', true)->first();
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'parent_student',
            'student_id',
            'parent_id'
        )->withPivot(['relationship_type', 'is_primary_contact'])
            ->withTimestamps();
    }

    /** Daily attendance history. */
    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class)->orderByDesc('date');
    }

    /** Assessment / exam records. */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /** Character & spiritual development notes. */
    public function behaviorNotes(): HasMany
    {
        return $this->hasMany(BehaviorNote::class)->orderByDesc('occurred_on');
    }

    /** Fee invoices. */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Payments made for this child. */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /* ---------------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------------- */

    /** The parent flagged as primary contact (fallback: first linked parent). */
    public function primaryParent(): ?User
    {
        return $this->parents()
            ->wherePivot('is_primary_contact', true)
            ->first() ?? $this->parents()->first();
    }

    /**
     * Attendance rate (%) between two dates (inclusive).
     * Late counts as present; excused absences are excluded from the denominator.
     */
    public function attendanceRate(?string $from = null, ?string $to = null): ?float
    {
        $query = $this->attendance();

        // whereDate() keeps comparisons correct even where DATE values
        // are stored with a trailing " 00:00:00" (SQLite dev database).
        if ($from) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date', '<=', $to);
        }

        $records = $query->get(['date', 'status']);

        $counted = $records->filter(
            fn ($row) => $row->status !== AttendanceStatus::Excused
        );

        if ($counted->isEmpty()) {
            return null;
        }

        $present = $counted->filter(
            fn ($row) => $row->status->countsAsPresent()
        )->count();

        return round(($present / $counted->count()) * 100, 1);
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', StudentStatus::Active);
    }

    public function scopeInClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }

    /** Free-text search across names / registration number. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('reg_no', 'like', "%{$term}%");
        });
    }
}
