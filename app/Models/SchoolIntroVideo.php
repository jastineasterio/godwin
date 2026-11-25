<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The school introduction video shown on the public website.
 *
 * Only one video should be active at a time. The actual file bytes live in
 * Laravel's public storage disk (storage/app/public/videos); only the relative
 * path and metadata are stored in the database.
 */
class SchoolIntroVideo extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'path',
        'original_name',
        'mime_type',
        'file_size',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'file_size' => 'integer',
        ];
    }

    /** The single active intro video. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->latest();
    }

    /** Fully-qualified public URL for the video file. */
    public function url(): string
    {
        if (!$this->path) {
            return '';
        }

        return asset('storage/' . ltrim($this->path, '/'));
    }

    /** Delete the physical file from storage when the record is removed. */
    public function deleteFile(): void
    {
        if ($this->path) {
            Storage::disk('public')->delete(ltrim($this->path, '/'));
        }
    }
}
