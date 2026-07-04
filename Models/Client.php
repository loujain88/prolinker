<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'wallet_balance', 'company_name',
        'country', 'phone', 'bio', 'avatar_path',
    ];

    protected $casts = [
        'wallet_balance' => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'client_id');
    }

    public function activeRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'client_id')
            ->whereIn('status', ['pending', 'in_progress', 'delivered']);
    }

    public function completedRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'client_id')
            ->where('status', 'completed');
    }

    // ── Wallet helpers ─────────────────────────────────────────────────────────

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->wallet_balance >= $amount;
    }

    /**
     * Debit the wallet — must be called inside a DB::transaction().
     */
    public function debitWallet(float $amount): void
    {
        if (! $this->hasSufficientBalance($amount)) {
            throw new \RuntimeException("Insufficient wallet balance for client #{$this->id}.");
        }
        $this->decrement('wallet_balance', $amount);
    }

    /**
     * Credit the wallet — must be called inside a DB::transaction().
     */
    public function creditWallet(float $amount): void
    {
        $this->increment('wallet_balance', $amount);
    }
}
