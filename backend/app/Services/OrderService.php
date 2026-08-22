<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Seller;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * OrderService
 *
 * Manages the complete order lifecycle with escrow logic.
 *
 * Escrow contract:
 *   - On place():     client wallet debited → escrow_amount set
 *   - On complete():  escrow released to seller (minus platform fee)
 *   - On cancel():    escrow returned to client wallet
 *   - On dispute resolution: DisputeService calls releaseToSeller() or refundToClient()
 *
 * AI Hook: Inject your pricing model suggestion into place() via the
 * optional $aiPriceSuggestion parameter — store it in the ai_price_suggestion
 * column (after uncommenting it in the migration) for later analysis.
 */
class OrderService
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {}

    // =========================================================================
    // ORDER LIFECYCLE
    // =========================================================================

    /**
     * Place a new order: validate funds, deduct escrow, create request.
     *
     * @param  User    $clientUser   Authenticated client user
     * @param  Service $service      The service being ordered
     * @param  array   $details      Order-specific details (requirements, files, etc.)
     * @param  float|null $customPrice  Override dynamic_price (e.g. after negotiation)
     * @return ServiceRequest
     */
    public function place(
        User    $clientUser,
        Service $service,
        array   $details = [],
        ?float  $customPrice = null,
        ?int    $requestedDeliveryDays = null
    ): ServiceRequest {
        $price = $customPrice ?? (float) $service->dynamic_price;
        $deliveryDays = $requestedDeliveryDays ?? $service->delivery_days;

        return DB::transaction(function () use ($clientUser, $service, $details, $price, $customPrice, $deliveryDays): ServiceRequest {
            // Lock the client row to prevent race conditions on wallet
            $client = Client::where('user_id', $clientUser->id)->lockForUpdate()->firstOrFail();
            $client->debitWallet($price); // Throws if insufficient balance

            // Calculate platform fee up-front and store it for auditability
            $feePercent  = (float) config('platform.fee_percent', 10);
            $platformFee = round($price * $feePercent / 100, 2);

            $order = ServiceRequest::create([
                'client_id'     => $client->id,
                'seller_id'     => $service->seller_id,
                'service_id'    => $service->id,
                'status'        => ServiceRequest::STATUS_PENDING,
                'final_price'   => $price,
                'custom_price'  => $customPrice, // null unless the client offered above listing
                'escrow_amount' => $price,    // full price held in escrow
                'platform_fee'  => $platformFee,
                'order_details' => $details,
                'deadline_at'   => now()->addDays($deliveryDays),
            ]);

            // Notify the seller
            $order->load('service');
            $this->notifications->orderPlaced($service->seller->user, $order);

            return $order;
        });
    }

    /**
     * Seller accepts the order: status → in_progress.
     *
     * @param  ServiceRequest $order
     * @param  User           $sellerUser
     * @return ServiceRequest
     */
    public function accept(ServiceRequest $order, User $sellerUser): ServiceRequest
    {
        $this->assertOrderStatus($order, ServiceRequest::STATUS_PENDING, 'accept');
        $this->assertSellerOwns($order, $sellerUser);

        $order->update(['status' => ServiceRequest::STATUS_IN_PROGRESS]);

        $this->notifications->orderAccepted($order->client->user, $order);

        return $order->fresh();
    }

    /**
     * Seller marks the order as delivered.
     *
     * @param  ServiceRequest $order
     * @param  User           $sellerUser
     * @return ServiceRequest
     */
    public function deliver(ServiceRequest $order, User $sellerUser): ServiceRequest
    {
        $this->assertOrderStatus($order, ServiceRequest::STATUS_IN_PROGRESS, 'deliver');
        $this->assertSellerOwns($order, $sellerUser);

        $order->update([
            'status'       => ServiceRequest::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $this->notifications->orderDelivered($order->client->user, $order);

        return $order->fresh();
    }

    /**
     * Client approves the delivery: status → completed, escrow released to seller.
     *
     * @param  ServiceRequest $order
     * @param  User           $clientUser
     * @param  int|null       $rating     1-5 stars (optional at this stage)
     * @param  string|null    $reviewText Optional written review
     * @return ServiceRequest
     */
    public function complete(
        ServiceRequest $order,
        User           $clientUser,
        ?int           $rating = null,
        ?string        $reviewText = null
    ): ServiceRequest {
        $this->assertOrderStatus($order, ServiceRequest::STATUS_DELIVERED, 'complete');
        $this->assertClientOwns($order, $clientUser);

        return DB::transaction(function () use ($order, $rating, $reviewText): ServiceRequest {
            $fees         = $order->calculateFees();
            $sellerPayout = $fees['seller_payout'];

            // Release escrow → seller wallet
            $seller = Seller::where('id', $order->seller_id)->lockForUpdate()->firstOrFail();
            $seller->creditWallet($sellerPayout);

            $order->update([
                'status'        => ServiceRequest::STATUS_COMPLETED,
                'escrow_amount' => 0,
                'completed_at'  => now(),
                'rating'        => $rating,
                'review_text'   => $reviewText,
                'reviewed_at'   => $rating ? now() : null,
            ]);

            // Update denormalised stats on seller and service
            $seller->recalculateStats();
            $order->service->recalculateRating();

            $this->notifications->orderCompleted($seller->user, $order, $sellerPayout);

            return $order->fresh();
        });
    }

    /**
     * Cancel an order: return escrow to client.
     * Only allowed when status is pending (before seller starts work).
     * Sellers or admins may cancel in-progress orders via a separate admin action.
     *
     * @param  ServiceRequest $order
     * @param  User           $actingUser  Client, seller, or admin
     * @param  string         $reason
     * @return ServiceRequest
     */
    public function cancel(ServiceRequest $order, User $actingUser, string $reason = ''): ServiceRequest
    {
        if (! in_array($order->status, [ServiceRequest::STATUS_PENDING, ServiceRequest::STATUS_IN_PROGRESS])) {
            throw new \RuntimeException("Order #{$order->id} cannot be cancelled in its current state.");
        }

        return DB::transaction(function () use ($order, $actingUser, $reason): ServiceRequest {
            // Refund escrow to client wallet
            if ($order->escrow_amount > 0) {
                $client = Client::where('id', $order->client_id)->lockForUpdate()->firstOrFail();
                $client->creditWallet($order->escrow_amount);
            }

            $order->update([
                'status'        => ServiceRequest::STATUS_CANCELLED,
                'escrow_amount' => 0,
            ]);

            // Notify both parties
            $this->notifications->orderCancelled($order->client->user, $order, $reason);
            $this->notifications->orderCancelled($order->seller->user, $order, $reason);

            return $order->fresh();
        });
    }

    // =========================================================================
    // ESCROW RELEASE METHODS (called by DisputeService)
    // =========================================================================

    /**
     * Release escrow to seller (used after a dispute is resolved in seller's favour).
     * Internal method — only DisputeService should call this.
     */
    public function releaseEscrowToSeller(ServiceRequest $order): void
    {
        $fees         = $order->calculateFees();
        $sellerPayout = $fees['seller_payout'];

        $seller = Seller::where('id', $order->seller_id)->lockForUpdate()->firstOrFail();
        $seller->creditWallet($sellerPayout);

        $order->update([
            'status'        => ServiceRequest::STATUS_COMPLETED,
            'escrow_amount' => 0,
            'completed_at'  => now(),
        ]);
    }

    /**
     * Refund escrow to client (used after a dispute is resolved in client's favour).
     * Internal method — only DisputeService should call this.
     */
    public function refundEscrowToClient(ServiceRequest $order): void
    {
        $client = Client::where('id', $order->client_id)->lockForUpdate()->firstOrFail();
        $client->creditWallet($order->escrow_amount);

        $order->update([
            'status'        => ServiceRequest::STATUS_REFUNDED,
            'escrow_amount' => 0,
        ]);
    }

    // =========================================================================
    // GUARDS
    // =========================================================================

    private function assertOrderStatus(ServiceRequest $order, string $expected, string $action): void
    {
        if ($order->status !== $expected) {
            throw new \RuntimeException(
                "Cannot {$action} order #{$order->id}: expected status '{$expected}', got '{$order->status}'."
            );
        }
    }

    private function assertSellerOwns(ServiceRequest $order, User $sellerUser): void
    {
        if ($order->seller->user_id !== $sellerUser->id) {
            throw new \RuntimeException("You do not own this order.");
        }
    }

    private function assertClientOwns(ServiceRequest $order, User $clientUser): void
    {
        if ($order->client->user_id !== $clientUser->id) {
            throw new \RuntimeException("You do not own this order.");
        }
    }
}
