<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The 'sellers' table holds profile and earnings data for users who
     * offer services. It has a strict 1:1 relationship with 'users'.
     *
     * AI Hook: 'skill_tags' (JSON) can be enriched by an NLP model that
     * reads the seller's bio and service descriptions to auto-suggest
     * relevant skill categories, improving search discoverability.
     *
     * AI Hook: 'quality_score' (float, 0-1) can be computed by an ML
     * model weighing ratings, delivery speed, dispute rate, and repeat
     * clients — used to rank sellers in search results.
     *
     * AI Hook: 'suggested_rate' (decimal) can be filled by a pricing model
     * that benchmarks this seller against similar profiles.
     */
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();

            // 1:1 link to users.
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // Earnings wallet. Funded when a request is marked 'completed'
            // and funds are released from the client's escrow balance.
            $table->decimal('wallet_balance', 12, 2)->default(0.00);

            // Professional profile
            $table->string('tagline', 255)->nullable(); // e.g. "Expert Laravel Developer"
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('portfolio_url')->nullable();

            // Seller performance snapshot (denormalised for fast reads)
            // These are updated by DB triggers or service-layer events,
            // not recomputed on every read.
            $table->unsignedInteger('total_orders_completed')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0.00); // 0.00 – 5.00
            $table->unsignedInteger('total_reviews')->default(0);

            // Verification status (manually set by admin or automated KYC)
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            // --- AI HOOK PLACEHOLDERS (commented out, ready to enable) ---
            // $table->json('skill_tags')->nullable();
            // $table->float('quality_score')->nullable();       // 0.0 – 1.0
            // $table->decimal('suggested_rate', 10, 2)->nullable();
            // $table->json('profile_embedding')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('average_rating');
            $table->index('is_verified');
            $table->index('total_orders_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
