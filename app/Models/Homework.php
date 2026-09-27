<?php

namespace App\Models;

use App\Enums\HomeworkType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Homework & class activities posted by teachers (visible to parents). */
class Homework extends Model
{
    /** Backing table is plural `homeworks` (Laravel would guess `homework`). */
    protected $table = 'homeworks';

    /** @var array<int, string> */
    protected $fillable = [
        'class_id',
        'subject_id',
        'teacher_id',
        'type',
        'title',
        'description',
        'assigned_on',
        'due_on',
        'attachments',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => HomeworkType::class,
            'assigned_on' => 'date',
            'due_on' => 'date',
            'attachments' => 'array',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** Items due on or after the given date (parent homework tracker). */
    public function scopeUpcoming(Builder $query, $from = null): Builder
    {
        return $query->whereDate('due_on', '>=', $from ?? now()->toDateString());
    }

    public function scopeForClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }
}
