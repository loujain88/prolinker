<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seller extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'wallet_balance', 'tagline', 'bio',
        'avatar_path', 'country', 'phone', 'portfolio_url',
        'total_orders_completed', 'average_rating', 'total_reviews',
        'is_verified', 'verified_at',
    ];

    protected $casts = [
        'wallet_balance'         => 'decimal:2',
        'average_rating'         => 'decimal:2',
        'is_verified'            => 'boolean',
        'verified_at'            => 'datetime',
        'total_orders_completed' => 'integer',
        'total_reviews'          => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function activeServices(): HasMany
    {
        return $this->hasMany(Service::class)->where('status', 'active');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'seller_id');
    }

    public function pendingRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'seller_id')
            ->where('status', 'pending');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class, 'user_id', 'user_id');
    }

    // ── Wallet helpers ────────────────────────────────────────────────────────

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->wallet_balance >= $amount;
    }

    /**
     * Debit wallet — must be called inside a DB::transaction().
     * Used when a withdrawal is placed (funds reserved).
     */
    public function debitWallet(float $amount): void
    {
        if (! $this->hasSufficientBalance($amount)) {
            throw new \RuntimeException("Insufficient wallet balance for seller #{$this->id}.");
        }
        $this->decrement('wallet_balance', $amount);
    }

    /**
     * Credit wallet — must be called inside a DB::transaction().
     * Used when an order is completed and escrow is released.
     */
    public function creditWallet(float $amount): void
    {
        $this->increment('wallet_balance', $amount);
    }

    // ── Stat helpers ──────────────────────────────────────────────────────────

    /**
     * Recalculate and persist denormalised stats from the requests table.
     * Called by RequestObserver on status → completed.
     */
    public function recalculateStats(): void
    {
        $stats = ServiceRequest::where('seller_id', $this->id)
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as total_completed, AVG(rating) as avg_rating, COUNT(rating) as total_reviews')
            ->first();

        $this->update([
            'total_orders_completed' => $stats->total_completed ?? 0,
            'average_rating'         => $stats->avg_rating ?? 0,
            'total_reviews'          => $stats->total_reviews ?? 0,
        ]);
    }
}
