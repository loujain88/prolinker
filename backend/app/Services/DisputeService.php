<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * DisputeService
 *
 * Handles the full dispute lifecycle:
 *   open → under_review → resolved (client | seller | split | no_action)
 *
 * Resolution actions trigger escrow release via OrderService,
 * maintaining a clean separation of financial and dispute concerns.
 *
 * AI Hook: After opening a dispute, call your AI model here and store
 * the result in dispute.ai_recommended_resolution. Show it only to
 * admins in the dispute detail view.
 */
class DisputeService
{
    public function __construct(
        private readonly OrderService        $orderService,
        private readonly NotificationService $notifications
    ) {}

    /**
     * Open a dispute against a delivered order.
     * Only the client may open a dispute, and only when status is 'delivered'.
     *
     * @param  ServiceRequest $order
     * @param  User           $clientUser
     * @param  string         $reason         The client's written grounds
     * @param  array          $evidenceFiles  Array of UploadedFile instances
     * @return Dispute
     */
    public function open(
        ServiceRequest $order,
        User           $clientUser,
        string         $reason,
        array          $evidenceFiles = []
    ): Dispute {
        if ($order->client->user_id !== $clientUser->id) {
            throw new \RuntimeException("Only the client who placed this order may open a dispute.");
        }

        if ($order->status !== ServiceRequest::STATUS_DELIVERED) {
            throw new \RuntimeException("Disputes can only be opened on delivered orders.");
        }

        if ($order->hasOpenDispute()) {
            throw new \RuntimeException("A dispute is already open for Order #{$order->id}.");
        }

        // Store any uploaded evidence files
        $evidencePaths = [];
        foreach ($evidenceFiles as $file) {
            $evidencePaths[] = $file->store("disputes/{$order->id}/evidence", 'private');
        }

        $dispute = DB::transaction(function () use ($order, $clientUser, $reason, $evidencePaths): Dispute {
            $dispute = Dispute::create([
                'request_id'     => $order->id,
                'opened_by'      => 'client',
                'reason'         => $reason,
                'evidence_paths' => $evidencePaths,
                'status'         => Dispute::STATUS_OPEN,
            ]);

            // Freeze the order — prevent seller from marking anything else
            $order->update(['status' => ServiceRequest::STATUS_DELIVERED]);

            return $dispute;
        });

        // Notify all admins (here we grab the first admin; adapt to your admin routing)
        $admin = User::where('role', 'admin')->where('is_active', true)->first();
        if ($admin) {
            $this->notifications->disputeOpened($admin, $dispute);
        }

        // Notify the seller — they need to know a dispute was opened and why
        $this->notifications->disputeOpenedForSeller($order->seller->user, $dispute);

        return $dispute;
    }

    /**
     * Admin assigns themselves to a dispute to begin review.
     *
     * @param  Dispute $dispute
     * @param  User    $admin
     * @return Dispute
     */
    public function assign(Dispute $dispute, User $admin): Dispute
    {
        if (! $admin->isAdmin()) {
            throw new \RuntimeException("Only admins can be assigned to disputes.");
        }

        if (! $dispute->isOpen()) {
            throw new \RuntimeException("Dispute #{$dispute->id} is not open for assignment.");
        }

        $dispute->update([
            'admin_id'    => $admin->id,
            'status'      => Dispute::STATUS_UNDER_REVIEW,
            'assigned_at' => now(),
        ]);

        return $dispute->fresh();
    }

    /**
     * Admin resolves a dispute in the CLIENT's favour → full refund.
     *
     * @param  Dispute $dispute
     * @param  User    $admin
     * @param  string  $adminNotes  Required: admin must document their ruling
     * @return Dispute
     */
    public function resolveForClient(Dispute $dispute, User $admin, string $adminNotes): Dispute
    {
        $this->assertAdminCanResolve($dispute, $admin);

        return DB::transaction(function () use ($dispute, $admin, $adminNotes): Dispute {
            // Refund escrow to client
            $this->orderService->refundEscrowToClient($dispute->request);

            $dispute->update([
                'status'      => Dispute::STATUS_RESOLVED_CLIENT,
                'admin_notes' => $adminNotes,
                'resolved_at' => now(),
            ]);

            // Notify both parties
            $this->notifications->disputeResolved($dispute->request->client->user, $dispute, 'Refund issued to client');
            $this->notifications->disputeResolved($dispute->request->seller->user, $dispute, 'Resolved in client\'s favour — order refunded');

            return $dispute->fresh();
        });
    }

    /**
     * Admin resolves a dispute in the SELLER's favour → escrow released.
     *
     * @param  Dispute $dispute
     * @param  User    $admin
     * @param  string  $adminNotes
     * @return Dispute
     */
    public function resolveForSeller(Dispute $dispute, User $admin, string $adminNotes): Dispute
    {
        $this->assertAdminCanResolve($dispute, $admin);

        return DB::transaction(function () use ($dispute, $admin, $adminNotes): Dispute {
            // Release escrow to seller
            $this->orderService->releaseEscrowToSeller($dispute->request);

            $dispute->update([
                'status'      => Dispute::STATUS_RESOLVED_SELLER,
                'admin_notes' => $adminNotes,
                'resolved_at' => now(),
            ]);

            $this->notifications->disputeResolved($dispute->request->client->user, $dispute, 'Resolved in seller\'s favour — payment released');
            $this->notifications->disputeResolved($dispute->request->seller->user, $dispute, 'Funds have been released to your wallet');

            return $dispute->fresh();
        });
    }

    /**
     * Admin closes a dispute without action (e.g. opened in error).
     *
     * @param  Dispute $dispute
     * @param  User    $admin
     * @param  string  $adminNotes
     * @return Dispute
     */
    public function closeWithNoAction(Dispute $dispute, User $admin, string $adminNotes): Dispute
    {
        $this->assertAdminCanResolve($dispute, $admin);

        $dispute->update([
            'status'      => Dispute::STATUS_CLOSED_NO_ACTION,
            'admin_notes' => $adminNotes,
            'resolved_at' => now(),
        ]);

        return $dispute->fresh();
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    private function assertAdminCanResolve(Dispute $dispute, User $admin): void
    {
        if (! $admin->isAdmin()) {
            throw new \RuntimeException("Only admins can resolve disputes.");
        }

        if ($dispute->isResolved()) {
            throw new \RuntimeException("Dispute #{$dispute->id} is already resolved.");
        }
    }
}
