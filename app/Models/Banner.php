<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Hero banner / slider slide for the public website (Administrator CMS). */
class Banner extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'title',
        'subtitle',
        'image_path',
        'link',
        'cta_label',
        'is_active',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Active slides ordered for the hero slider. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
