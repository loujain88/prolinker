<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The 'clients' table holds profile data specific to users who purchase
     * services. It has a strict 1:1 relationship with 'users'.
     *
     * AI Hook: 'preference_tags' (JSON) can store AI-inferred interest
     * categories derived from browsing and order history, powering
     * personalised service recommendations on the homepage.
     *
     * AI Hook: 'churn_risk_score' (float, 0-1) can be populated by a
     * scheduled ML job to flag clients likely to become inactive,
     * enabling targeted retention campaigns.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            // 1:1 link to users. Cascade delete keeps the DB consistent.
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // Escrow-style wallet. Funds deposited here are held until a
            // request is completed, then transferred to the seller's wallet.
            $table->decimal('wallet_balance', 12, 2)->default(0.00);

            // Optional extended profile fields
            $table->string('company_name')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();

            // --- AI HOOK PLACEHOLDERS (commented out, ready to enable) ---
            // $table->json('preference_tags')->nullable();
            // $table->float('churn_risk_score')->nullable();
            // $table->json('profile_embedding')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('wallet_balance'); // Useful for financial reports
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
