<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * God-Win SMS system user.
 *
 * RBAC roles: admin | senior_pastor | head_of_school | teacher | accountant | parent.
 * Students are NOT users — parents link to children via the `parent_student` pivot.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'avatar_path',
        'last_login_at',
        'email_verified_at',
    ];

    /** @var array<int, string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    /**
     * Children linked to this parent account (MULTI-CHILD PORTAL).
     * Pivot carries relationship_type + is_primary_contact.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(
            Student::class,
            'parent_student',
            'parent_id',
            'student_id'
        )->withPivot(['relationship_type', 'is_primary_contact'])
            ->withTimestamps();
    }

    /** Alias used by the parent portal child switcher. */
    public function children(): BelongsToMany
    {
        return $this->students();
    }

    /** Classes where this user is the class teacher. */
    public function classesTaught(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'teacher_id');
    }

    /** Subjects allocated to this teacher. */
    public function subjectsTaught(): BelongsToMany
    {
        return $this->belongsToMany(
            Subject::class,
            'class_subject',
            'teacher_id',
            'subject_id'
        )->withPivot('class_id')->withTimestamps();
    }

    /** Timetable slots taught by this user. */
    public function timetableSlots(): HasMany
    {
        return $this->hasMany(Timetable::class, 'teacher_id');
    }

    /** Attendance rolls marked by this teacher. */
    public function attendancesMarked(): HasMany
    {
        return $this->hasMany(Attendance::class, 'marked_by');
    }

    /** Assessments recorded by this user. */
    public function assessmentsRecorded(): HasMany
    {
        return $this->hasMany(Assessment::class, 'recorded_by');
    }

    /** Homework / activities posted by this teacher. */
    public function homeworksPosted(): HasMany
    {
        return $this->hasMany(Homework::class, 'teacher_id');
    }

    /** Character / spiritual notes written by this user. */
    public function behaviorNotesWritten(): HasMany
    {
        return $this->hasMany(BehaviorNote::class, 'noted_by');
    }

    /** Announcements authored by this user. */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'author_id');
    }

    /** Events created by this user. */
    public function eventsCreated(): HasMany
    {
        return $this->hasMany(SchoolEvent::class, 'created_by');
    }

    /** Messaging conversations this user participates in. */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /** Messages sent by this user. */
    public function messagesSent(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /** Payments received by this user (Accountant). */
    public function paymentsReceived(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    /** Expenses recorded by this user (Accountant). */
    public function expensesRecorded(): HasMany
    {
        return $this->hasMany(Expense::class, 'recorded_by');
    }

    /** Invoices issued by this user. */
    public function invoicesIssued(): HasMany
    {
        return $this->hasMany(Invoice::class, 'issued_by');
    }

    /** Applications this user reviewed. */
    public function applicationsReviewed(): HasMany
    {
        return $this->hasMany(SchoolApplication::class, 'reviewed_by');
    }

    /** Audit trail generated by this user. */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /* ---------------------------------------------------------------------- */
    /* RBAC helpers */
    /* ---------------------------------------------------------------------- */

    /** True when the account is currently allowed to sign in. */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** True for any staff role (everyone except Parent) and only if active. */
    public function isStaff(): bool
    {
        return $this->role->isStaff() && $this->isActive();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isParent(): bool
    {
        return $this->role === UserRole::Parent;
    }

    /** Usage: $user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool]) */
    public function hasAnyRole(UserRole|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return in_array($this->role, $roles, true);
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopeRole(Builder $query, UserRole $role): Builder
    {
        return $query->where('role', $role->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('role', array_values(array_diff(
            UserRole::values(),
            [UserRole::Parent->value]
        )));
    }

    public function scopeParents(Builder $query): Builder
    {
        return $query->where('role', UserRole::Parent);
    }

    /* ---------------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------------- */

    /** Only the attributes safe to share with the Inertia frontend. */
    public function toAuthArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'status' => $this->status->value,
            'avatar_url' => $this->avatar_path,
        ];
    }
}
