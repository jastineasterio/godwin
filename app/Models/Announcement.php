<?php

namespace App\Models;

use App\Enums\AnnouncementType;
use App\Enums\Audience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Announcement / news post — powers both the PUBLIC website feed
 * and role-scoped in-app broadcasts (parents / staff / teachers).
 */
class Announcement extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'author_id',
        'type',
        'audience',
        'title',
        'slug',
        'excerpt',
        'body',
        'cover_image',
        'is_pinned',
        'is_published',
        'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'audience' => Audience::class,
            'is_pinned' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** Auto-generate a URL-friendly slug before creating a record. */
    protected static function booted(): void
    {
        static::creating(function (self $announcement) {
            if (! $announcement->slug) {
                $announcement->slug = Str::slug($announcement->title)
                    .'-'.Str::lower(Str::random(6));
            }
        });
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships */
    /* ---------------------------------------------------------------------- */

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes */
    /* ---------------------------------------------------------------------- */

    /** Public website feed: published, newest first, pinned items on top. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');
    }

    /** Only announcements relevant to a given audience. */
    public function scopeForAudience(Builder $query, Audience $audience): Builder
    {
        return $query->where(function (Builder $q) use ($audience) {
            $q->where('audience', Audience::Everyone->value)
                ->orWhere('audience', $audience->value);
        });
    }

    public function scopeOfType(Builder $query, AnnouncementType $type): Builder
    {
        return $query->where('type', $type->value);
    }
}
