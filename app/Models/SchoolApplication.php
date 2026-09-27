<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\RelationshipType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Online application submitted from the public website "Apply Now" form.
 * Named SchoolApplication to keep the model namespace unambiguous.
 * Applicants never get login credentials — staff review and enroll them.
 */
class SchoolApplication extends Model
{
    use SoftDeletes;

    /** Backing table (`applications`). */
    protected $table = 'applications';

    /** @var array<int, string> */
    protected $fillable = [
        'reference_no',
        'parent_name',
        'parent_email',
        'parent_phone',
        'relationship',
        'child_first_name',
        'child_last_name',
        'gender',
        'dob',
        'class_id',
        'address',
        'message',
        'status',
        'reviewed_by',
        'review_notes',
        'submitted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'relationship' => RelationshipType::class,
            'gender' => Gender::class,
            'dob' => 'date',
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Accessors */
    /* ---------------------------------------------------------------------- */

    /** "John Doe" of the prospective child. */
    public function getChildNameAttribute(): string
    {
        return trim("{$this->child_first_name} {$this->child_last_name}");
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    /** Class the child applied to join. */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** Staff member who reviewed the application. */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ApplicationStatus::Submitted->value,
            ApplicationStatus::UnderReview->value,
        ]);
    }

    public function scopeForStatus(Builder $query, ApplicationStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
