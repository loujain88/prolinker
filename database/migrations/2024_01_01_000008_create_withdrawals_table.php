<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The 'withdrawals' table records money flowing OUT of the platform to
     * sellers (and potentially clients requesting refunds to their bank).
     *
     * Flow:
     *   Seller submits withdrawal (status = pending)
     *   → Wallet balance is immediately RESERVED (deducted from available balance)
     *   → Admin reviews and processes payout
     *   → Admin approves  → status = approved, payout sent externally,
     *                        transaction_receipt stored as proof
     *   → Admin rejects   → status = rejected, reserved amount returned to wallet
     *
     * 'transaction_receipt' stores:
     *   a) A payment gateway payout confirmation ID, or
     *   b) A file path to a bank transfer receipt uploaded by the admin
     *
     * AI Hook: 'risk_hold_flag' (boolean) can be set by a model that
     * identifies high-risk withdrawal patterns (e.g. large amount immediately
     * after account creation) and routes them to enhanced admin review.
     */
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();

            // Withdrawals are always linked to a seller (or client refund)
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            // Currency code (ISO 4217)
            $table->string('currency', 3)->default('USD');

            // Lifecycle status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // Nullable: populated by admin when the payout is sent.
            // Could be a bank reference, PayPal transaction ID, crypto tx hash, etc.
            $table->string('transaction_receipt')->nullable()
                ->comment('Payout confirmation ID or file path to receipt uploaded by admin');

            // Withdrawal destination details (where to send the money)
            $table->string('payout_method', 50)->nullable(); // bank_transfer, paypal, wise, etc.
            $table->json('payout_details')->nullable();       // Account number, IBAN, PayPal email, etc.
                                                              // NEVER store raw card numbers here

            // Optional notes
            $table->text('seller_notes')->nullable();  // Reason/context from seller
            $table->text('admin_notes')->nullable();   // Admin's processing notes

            // Processing audit trail
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ── AI HOOK PLACEHOLDERS (commented out, ready to enable) ────
            // $table->boolean('risk_hold_flag')->default(false);
            // $table->float('risk_score')->nullable();            // 0.0 – 1.0
            // $table->json('risk_check_result')->nullable();

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────
            $table->index('status');
            $table->index(['user_id', 'status']);
            $table->index('payout_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
