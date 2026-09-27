<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable audit trail row (Administrator module).
 * `auditable` polymorphically targets the record that was changed.
 */
class AuditLog extends Model
{
    /** Audit rows are append-only — never mass-assign the timestamp. */
    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'auditable_type',
        'auditable_id',
        'properties',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    /** User who performed the action (NULL = system/cron action). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The affected record (Student, Invoice, User, settings row...). */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /* ---------------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------------- */

    /**
     * Persist an audit entry (call from controllers/services).
     *
     * AuditLog::record($user, 'student.updated', 'Updated student profile', $student, [
     *     'before' => $before, 'after' => $after,
     * ]);
     */
    public static function record(
        ?User $user,
        string $action,
        ?string $description = null,
        ?Model $auditable = null,
        ?array $properties = null
    ): static {
        return static::create([
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'properties' => $properties,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
