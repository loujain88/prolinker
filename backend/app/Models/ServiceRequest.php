<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRequest extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Maps to the 'requests' table.
     * We name the Model 'ServiceRequest' to avoid clashing with Laravel's
     * own \Illuminate\Http\Request class in controller imports.
     */
    protected $table = 'requests';

    protected $fillable = [
        'client_id', 'seller_id', 'service_id',
        'status', 'final_price', 'custom_price', 'escrow_amount', 'platform_fee',
        'order_details', 'delivery_files', 'delivery_notes', 'deadline_at',
        'delivered_at', 'completed_at',
        'rating', 'review_text', 'reviewed_at',
    ];

    protected $casts = [
        'final_price'   => 'decimal:2',
        'custom_price'  => 'decimal:2',
        'escrow_amount' => 'decimal:2',
        'platform_fee'  => 'decimal:2',
        'order_details' => 'array',
        'delivery_files'=> 'array',
        'deadline_at'   => 'datetime',
        'delivered_at'  => 'datetime',
        'completed_at'  => 'datetime',
        'reviewed_at'   => 'datetime',
        'rating'        => 'integer',
    ];

    // ── Status constants ──────────────────────────────────────────────────────

    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_DELIVERED   = 'delivered';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELLED   = 'cancelled';
    const STATUS_REFUNDED    = 'refunded';

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeForSeller($query, int $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * The dispute filed against this order (at most one).
     */
    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class, 'request_id');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'request_id');
    }

    // ── Business logic helpers ────────────────────────────────────────────────

    public function isPending(): bool     { return $this->status === self::STATUS_PENDING; }
    public function isInProgress(): bool  { return $this->status === self::STATUS_IN_PROGRESS; }
    public function isDelivered(): bool   { return $this->status === self::STATUS_DELIVERED; }
    public function isCompleted(): bool   { return $this->status === self::STATUS_COMPLETED; }
    public function isCancelled(): bool   { return $this->status === self::STATUS_CANCELLED; }
    public function isRefunded(): bool    { return $this->status === self::STATUS_REFUNDED; }

    public function hasOpenDispute(): bool
    {
        return $this->dispute()->whereIn('status', ['open', 'under_review'])->exists();
    }

    /**
     * Calculate the platform commission and the net seller payout.
     * Uses the PLATFORM_FEE_PERCENT env variable (default 10%).
     */
    public function calculateFees(): array
    {
        $feePercent    = (float) config('platform.fee_percent', 10);
        $platformFee   = round($this->final_price * $feePercent / 100, 2);
        $sellerPayout  = round($this->final_price - $platformFee, 2);

        return [
            'platform_fee'  => $platformFee,
            'seller_payout' => $sellerPayout,
        ];
    }
}
