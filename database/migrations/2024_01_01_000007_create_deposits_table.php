<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The 'deposits' table records money flowing INTO the platform from clients
     * (and optionally sellers topping up their balance).
     *
     * Flow:
     *   Client submits deposit request (status = pending)
     *   → Admin reviews receipt / payment gateway confirms
     *   → Admin approves  → status = approved, client wallet_balance += amount
     *   → Admin rejects   → status = rejected, no wallet change
     *
     * 'transaction_receipt' stores either:
     *   a) A file path to an uploaded bank-transfer screenshot, or
     *   b) A payment-gateway transaction ID string (Stripe, PayPal, etc.)
     *
     * AI Hook: 'fraud_check_result' (json) can store the output of an ML
     * fraud-detection model that evaluates the receipt image or gateway
     * metadata before the admin review step, auto-approving low-risk deposits.
     */
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();

            // Deposits are always linked to a user (client or admin topping up)
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            // Currency code (ISO 4217) — default USD; extend for multi-currency
            $table->string('currency', 3)->default('USD');

            // Lifecycle status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // Nullable: client must upload a proof of payment or provide a
            // gateway transaction ID. Null is valid for gateway-auto-confirmed deposits.
            $table->string('transaction_receipt')->nullable()
                ->comment('File path to uploaded receipt OR payment gateway transaction ID');

            // Payment method used (bank_transfer, stripe, paypal, crypto, etc.)
            $table->string('payment_method', 50)->nullable();

            // Optional notes from the admin when approving or rejecting
            $table->text('admin_notes')->nullable();

            // Timestamps for audit
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Admin who processed this deposit
            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ── AI HOOK PLACEHOLDERS (commented out, ready to enable) ────
            // $table->json('fraud_check_result')->nullable();
            // $table->float('fraud_risk_score')->nullable();      // 0.0 – 1.0
            // $table->boolean('auto_approved')->default(false);

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────
            $table->index('status');
            $table->index(['user_id', 'status']);
            $table->index('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
