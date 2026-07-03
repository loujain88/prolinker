<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Custom notifications table. While Laravel ships with a polymorphic
     * 'notifications' table via `php artisan notifications:table`, this
     * bespoke version is simpler and purpose-built for the marketplace,
     * making it easier to query and display in-app notification centres.
     *
     * If you later want to use Laravel's built-in notification channels
     * (mail, SMS, Slack, broadcast), you can keep this table as the
     * persistence layer and dispatch Laravel Notifications from your
     * service layer, which will call `toDatabase()` and write here.
     *
     * AI Hook: 'priority_score' (float) can rank notifications so that
     * time-sensitive or high-value alerts (large order, dispute opened)
     * surface at the top of the in-app bell icon ahead of lower-signal
     * events, personalised per user by a model trained on click-through data.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // The recipient of this notification
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Machine-readable event type for conditional rendering in Vue
            // e.g. 'request.status_changed', 'dispute.opened', 'deposit.approved'
            $table->string('type', 100);

            // Human-readable notification body (can contain basic HTML or markdown)
            $table->text('content');

            // Optional deep-link so the UI can route the user on click
            $table->string('action_url')->nullable();

            // Polymorphic reference to the entity that triggered this notification
            // (a request, dispute, deposit, etc.) — allows the UI to fetch
            // fresh data about the linked entity.
            $table->string('notifiable_type')->nullable(); // e.g. 'App\Models\Request'
            $table->unsignedBigInteger('notifiable_id')->nullable();

            // NULL = unread; populated when the user views/dismisses it
            $table->timestamp('read_at')->nullable();

            // ── AI HOOK PLACEHOLDERS (commented out, ready to enable) ────
            // $table->float('priority_score')->nullable();    // Higher = show first
            // $table->boolean('ai_generated')->default(false); // Flag AI-written content

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            // ── Indexes ──────────────────────────────────────────────────
            $table->index(['user_id', 'read_at']); // Fast unread-count queries
            $table->index('type');
            $table->index(['notifiable_type', 'notifiable_id']); // Polymorphic lookup
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
