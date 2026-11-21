<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A school asset / inventory item (furniture, electronics, teaching materials, …).
 * Soft-deleted so disposal history is preserved rather than erased.
 */
class Asset extends Model
{
    use HasFactory, SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'category',
        'asset_code',
        'description',
        'quantity',
        'purchase_date',
        'purchase_price',
        'supplier',
        'location',
        'condition',
        'status',
        'image_path',
        'recorded_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
        ];
    }

    /* ---------------------------------------------------------------------- */
    /* Relationships                                                          */
    /* ---------------------------------------------------------------------- */

    /** User who registered the asset. */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /* ---------------------------------------------------------------------- */
    /* Query scopes                                                           */
    /* ---------------------------------------------------------------------- */

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeCondition(Builder $query, string $condition): Builder
    {
        return $query->where('condition', $condition);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('asset_code', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }
}
