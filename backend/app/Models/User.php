<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'phone', 'id_document_path',
        'approval_status', 'approval_notes', 'approved_at', 'is_super_admin',
    ];

    protected $hidden = ['password', 'remember_token', 'id_document_path'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
        'approved_at'       => 'datetime',
        'is_super_admin'    => 'boolean',
    ];

    public function isApproved(): bool { return $this->approval_status === 'approved'; }
    public function isSuperAdmin(): bool { return $this->role === 'admin' && $this->is_super_admin; }

    // ── Role helpers ──────────────────────────────────────────────────────────

    public function isAdmin(): bool  { return $this->role === 'admin'; }
    public function isClient(): bool { return $this->role === 'client'; }
    public function isSeller(): bool { return $this->role === 'seller'; }
    public function isActive(): bool { return (bool) $this->is_active; }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function seller(): HasOne
    {
        return $this->hasOne(Seller::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications(): HasMany
    {
        return $this->hasMany(Notification::class)->whereNull('read_at');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function assignedDisputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'admin_id');
    }
}
