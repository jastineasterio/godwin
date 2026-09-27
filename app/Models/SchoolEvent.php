<?php

namespace App\Models;

use App\Enums\Audience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * School calendar event (named SchoolEvent to avoid clashing with
 * Laravel's event dispatcher naming in code).
 */
class SchoolEvent extends Model
{
    /** Backing table (`events`) — prefix the class name, not the table. */
    protected $table = 'events';

    /** @var array<int, string> */
    protected $fillable = [
        'created_by',
        'title',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'location',
        'color',
        'audience',
        'is_published',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'audience' => Audience::class,
            'is_published' => 'boolean',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('start_date', '>=', now()->toDateString())
            ->orderBy('start_date');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
