<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'seller_id', 'category_id', 'title', 'description',
        'rate', 'dynamic_price', 'delivery_days', 'revisions_included',
        'status', 'thumbnail_path', 'gallery_paths',
        'average_rating', 'total_orders',
    ];

    protected $casts = [
        'rate'               => 'decimal:2',
        'dynamic_price'      => 'decimal:2',
        'average_rating'     => 'decimal:2',
        'gallery_paths'      => 'array',
        'total_orders'       => 'integer',
        'delivery_days'      => 'integer',
        'revisions_included' => 'integer',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCategory($query, ?int $categoryId)
    {
        return $categoryId ? $query->where('category_id', $categoryId) : $query;
    }

    public function scopeByPriceRange($query, ?float $min, ?float $max)
    {
        if ($min !== null) $query->where('dynamic_price', '>=', $min);
        if ($max !== null) $query->where('dynamic_price', '<=', $max);
        return $query;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) return $query;
        return $query->whereFullText(['title', 'description'], $term)
            ->orWhere('title', 'like', "%{$term}%");
    }

    public function scopeSortBy($query, string $sort = 'newest')
    {
        return match ($sort) {
            'price_asc'  => $query->orderBy('dynamic_price', 'asc'),
            'price_desc' => $query->orderBy('dynamic_price', 'desc'),
            'rating'     => $query->orderBy('average_rating', 'desc'),
            'popular'    => $query->orderBy('total_orders', 'desc'),
            default      => $query->orderBy('created_at', 'desc'),
        };
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    // ── Stat helpers ──────────────────────────────────────────────────────────

    /**
     * Recalculate denormalised rating stats. Called by RequestObserver.
     */
    public function recalculateRating(): void
    {
        $stats = $this->requests()
            ->where('status', 'completed')
            ->whereNotNull('rating')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_orders')
            ->first();

        $this->update([
            'average_rating' => round($stats->avg_rating ?? 0, 2),
            'total_orders'   => $stats->total_orders ?? 0,
        ]);
    }
}
