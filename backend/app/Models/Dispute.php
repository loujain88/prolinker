<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id', 'admin_id', 'opened_by', 'reason',
        'evidence_paths', 'status', 'admin_notes',
        'assigned_at', 'resolved_at',
    ];

    protected $casts = [
        'evidence_paths' => 'array',
        'assigned_at'    => 'datetime',
        'resolved_at'    => 'datetime',
    ];

    // ── Status constants ──────────────────────────────────────────────────────

    const STATUS_OPEN              = 'open';
    const STATUS_UNDER_REVIEW      = 'under_review';
    const STATUS_RESOLVED_CLIENT   = 'resolved_client';
    const STATUS_RESOLVED_SELLER   = 'resolved_seller';
    const STATUS_RESOLVED_SPLIT    = 'resolved_split';
    const STATUS_CLOSED_NO_ACTION  = 'closed_no_action';

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * The order this dispute was raised against.
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    /**
     * The admin mediating this dispute (nullable until assigned).
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    // ── Business logic helpers ────────────────────────────────────────────────

    public function isOpen(): bool         { return $this->status === self::STATUS_OPEN; }
    public function isUnderReview(): bool  { return $this->status === self::STATUS_UNDER_REVIEW; }
    public function isResolved(): bool
    {
        return in_array($this->status, [
            self::STATUS_RESOLVED_CLIENT,
            self::STATUS_RESOLVED_SELLER,
            self::STATUS_RESOLVED_SPLIT,
            self::STATUS_CLOSED_NO_ACTION,
        ]);
    }
}
