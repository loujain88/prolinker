<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'amount', 'currency', 'status',
        'transaction_receipt', 'payout_method', 'payout_details',
        'seller_notes', 'admin_notes',
        'approved_at', 'rejected_at', 'processed_by',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'payout_details' => 'array',
        'approved_at'    => 'datetime',
        'rejected_at'    => 'datetime',
    ];

    // ── Status constants ──────────────────────────────────────────────────────

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * The user (seller) who submitted this withdrawal.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin who processed this withdrawal.
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }
}
