<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The 'disputes' table has a 1:0..1 relationship with 'requests'.
     * Only one dispute may be opened per request. An admin user is assigned
     * to mediate and reaches a resolution that triggers a wallet action
     * (refund to client or release to seller).
     *
     * AI Hook: 'ai_recommended_resolution' (string/json) can be pre-filled
     * by an LLM that reads the order_details, chat history, and both parties'
     * statements to suggest a fair outcome — shown only to the admin.
     *
     * AI Hook: 'fraud_signal_score' (float) can flag disputes that pattern-
     * match known abuse patterns (e.g. serial refund requesters), surfacing
     * them to admins with higher priority.
     */
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();

            // 1:0..1 — each request can have at most one dispute.
            // unique() enforces the 0..1 cardinality at the DB level.
            $table->foreignId('request_id')
                ->unique()
                ->constrained('requests')
                ->restrictOnDelete(); // Never silently delete a disputed request

            // The admin user assigned to mediate this dispute.
            // Nullable: dispute is unassigned until an admin picks it up.
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Which party opened the dispute
            $table->enum('opened_by', ['client', 'seller']);

            // Free-text reason submitted by the party who opened the dispute
            $table->text('reason');

            // Evidence attachments (JSON array of file paths)
            $table->json('evidence_paths')->nullable();

            // ── Status lifecycle ─────────────────────────────────────────
            // open → under_review → resolved_client  (refund to client)
            //                    → resolved_seller   (release to seller)
            //                    → resolved_split    (partial split — future)
            //                    → closed_no_action  (e.g. opened in error)
            $table->enum('status', [
                'open',
                'under_review',
                'resolved_client',
                'resolved_seller',
                'resolved_split',
                'closed_no_action',
            ])->default('open');

            // Admin's final written ruling, visible to both parties
            $table->text('admin_notes')->nullable();

            // Timestamps for SLA tracking
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            // ── AI HOOK PLACEHOLDERS (commented out, ready to enable) ────
            // $table->json('ai_recommended_resolution')->nullable();
            // $table->float('fraud_signal_score')->nullable();        // 0.0 – 1.0
            // $table->text('ai_case_summary')->nullable();

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────
            $table->index('status');
            $table->index('admin_id');
            $table->index('opened_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
