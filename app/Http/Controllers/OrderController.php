<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * OrderController
 *
 * Manages the full order (ServiceRequest) lifecycle.
 *
 * Status flow:
 *   Client places order  → pending
 *   Seller accepts       → in_progress
 *   Seller delivers      → delivered
 *   Client approves      → completed  (escrow released to seller)
 *   Dispute opened       → escrow frozen (handled by DisputeController)
 *
 * All financial mutations are delegated to OrderService which wraps them
 * in DB transactions — this controller only handles HTTP concerns.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    // =========================================================================
    // CLIENT ACTIONS
    // =========================================================================

    /**
     * POST /api/v1/orders
     *
     * Client places an order for a service.
     *
     * Body (JSON):
     *   - service_id    int     required
     *   - order_details object  optional (requirements, deadline preferences, files)
     */
    public function place(Request $request): JsonResponse
    {
        $this->gate('client');

        $validated = $request->validate([
            'service_id'    => ['required', 'integer', 'exists:services,id'],
            'order_details' => ['nullable', 'array'],
            'order_details.requirements'    => ['nullable', 'string', 'max:5000'],
            'order_details.preferred_style' => ['nullable', 'string', 'max:1000'],
        ]);

        $service = Service::with('seller.user')->findOrFail($validated['service_id']);

        if ($service->status !== 'active') {
            return response()->json(['message' => 'This service is not currently available.'], 422);
        }

        $order = $this->orderService->place(
            clientUser: Auth::user(),
            service:    $service,
            details:    $validated['order_details'] ?? [],
        );

        return response()->json([
            'message' => 'Order placed successfully. Funds have been held in escrow.',
            'data'    => $this->formatOrder($order->load(['client.user', 'seller.user', 'service'])),
        ], 201);
    }

    /**
     * PATCH /api/v1/orders/{id}/complete
     *
     * Client approves a delivered order → escrow released to seller.
     *
     * Body (JSON):
     *   - rating      int     optional (1-5)
     *   - review_text string  optional
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $this->gate('client');

        $validated = $request->validate([
            'rating'      => ['nullable', 'integer', 'min:1', 'max:5'],
            'review_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = $this->findOrderForClient($id);

        $order = $this->orderService->complete(
            order:      $order,
            clientUser: Auth::user(),
            rating:     $validated['rating'] ?? null,
            reviewText: $validated['review_text'] ?? null,
        );

        return response()->json([
            'message' => 'Order completed. Funds released to the seller.',
            'data'    => $this->formatOrder($order->load(['client.user', 'seller.user', 'service'])),
        ]);
    }

    /**
     * PATCH /api/v1/orders/{id}/cancel
     *
     * Client cancels a pending order. Escrow is refunded.
     *
     * Body (JSON):
     *   - reason  string  optional
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $this->gate('client');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->findOrderForClient($id);

        if (! in_array($order->status, [ServiceRequest::STATUS_PENDING])) {
            return response()->json([
                'message' => 'Orders can only be cancelled while in pending status. For in-progress orders, please open a dispute.',
            ], 422);
        }

        $order = $this->orderService->cancel($order, Auth::user(), $validated['reason'] ?? '');

        return response()->json([
            'message' => 'Order cancelled. Your funds have been returned to your wallet.',
            'data'    => $this->formatOrder($order),
        ]);
    }

    /**
     * GET /api/v1/orders/my
     *
     * Client's own orders, filterable by status.
     */
    public function myOrders(Request $request): JsonResponse
    {
        $this->gate('client');

        $client = Auth::user()->client;

        $orders = ServiceRequest::forClient($client->id)
            ->with(['service', 'seller.user', 'dispute'])
            ->when($request->status, fn($q, $s) => $q->withStatus($s))
            ->latest()
            ->paginate(15);

        return response()->json(['data' => $orders]);
    }

    // =========================================================================
    // SELLER ACTIONS
    // =========================================================================

    /**
     * PATCH /api/v1/orders/{id}/accept
     *
     * Seller accepts a pending order → in_progress.
     */
    public function accept(int $id): JsonResponse
    {
        $this->gate('seller');

        $order = $this->findOrderForSeller($id);
        $order = $this->orderService->accept($order, Auth::user());

        return response()->json([
            'message' => 'Order accepted. Work is now in progress.',
            'data'    => $this->formatOrder($order->load(['client.user', 'service'])),
        ]);
    }

    /**
     * PATCH /api/v1/orders/{id}/deliver
     *
     * Seller marks order as delivered → awaiting client approval.
     */
    public function deliver(int $id): JsonResponse
    {
        $this->gate('seller');

        $order = $this->findOrderForSeller($id);
        $order = $this->orderService->deliver($order, Auth::user());

        return response()->json([
            'message' => 'Order marked as delivered. Awaiting client approval.',
            'data'    => $this->formatOrder($order->load(['client.user', 'service'])),
        ]);
    }

    /**
     * GET /api/v1/seller/orders
     *
     * Seller's incoming orders, filterable by status.
     */
    public function sellerOrders(Request $request): JsonResponse
    {
        $this->gate('seller');

        $seller = Auth::user()->seller;

        $orders = ServiceRequest::forSeller($seller->id)
            ->with(['service', 'client.user', 'dispute'])
            ->when($request->status, fn($q, $s) => $q->withStatus($s))
            ->latest()
            ->paginate(15);

        return response()->json(['data' => $orders]);
    }

    // =========================================================================
    // SHARED / ADMIN
    // =========================================================================

    /**
     * GET /api/v1/orders/{id}
     *
     * Fetch a single order. Accessible by:
     *   - The client who placed it
     *   - The seller who received it
     *   - Any admin
     */
    public function show(int $id): JsonResponse
    {
        $user  = Auth::user();
        $order = ServiceRequest::with([
            'client.user', 'seller.user', 'service', 'dispute.admin'
        ])->findOrFail($id);

        $canView = $user->isAdmin()
            || ($user->isClient() && $order->client->user_id === $user->id)
            || ($user->isSeller() && $order->seller->user_id === $user->id);

        if (! $canView) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(['data' => $this->formatOrder($order)]);
    }

    /**
     * GET /api/v1/admin/orders
     *
     * Admin: list all orders with optional filters.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $this->gate('admin');

        $orders = ServiceRequest::with(['client.user', 'seller.user', 'service', 'dispute'])
            ->when($request->status,    fn($q, $s) => $q->withStatus($s))
            ->when($request->client_id, fn($q, $v) => $q->forClient($v))
            ->when($request->seller_id, fn($q, $v) => $q->forSeller($v))
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $orders]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function findOrderForClient(int $id): ServiceRequest
    {
        $client = Auth::user()->client;
        return ServiceRequest::where('id', $id)
            ->where('client_id', $client->id)
            ->with(['service', 'seller.user'])
            ->firstOrFail();
    }

    private function findOrderForSeller(int $id): ServiceRequest
    {
        $seller = Auth::user()->seller;
        return ServiceRequest::where('id', $id)
            ->where('seller_id', $seller->id)
            ->with(['service', 'client.user'])
            ->firstOrFail();
    }

    private function formatOrder(ServiceRequest $order): array
    {
        return [
            'id'             => $order->id,
            'status'         => $order->status,
            'final_price'    => $order->final_price,
            'escrow_amount'  => $order->escrow_amount,
            'platform_fee'   => $order->platform_fee,
            'order_details'  => $order->order_details,
            'deadline_at'    => $order->deadline_at?->toIso8601String(),
            'delivered_at'   => $order->delivered_at?->toIso8601String(),
            'completed_at'   => $order->completed_at?->toIso8601String(),
            'rating'         => $order->rating,
            'review_text'    => $order->review_text,
            'has_dispute'    => $order->relationLoaded('dispute') && $order->dispute !== null,
            'service'        => $order->relationLoaded('service') ? [
                'id'    => $order->service->id,
                'title' => $order->service->title,
            ] : null,
            'client'         => $order->relationLoaded('client') ? [
                'id'   => $order->client->id,
                'name' => $order->client->user->name,
            ] : null,
            'seller'         => $order->relationLoaded('seller') ? [
                'id'   => $order->seller->id,
                'name' => $order->seller->user->name,
            ] : null,
            'created_at'     => $order->created_at->toIso8601String(),
        ];
    }

    private function gate(string $role): void
    {
        $user = Auth::user();
        if (! $user?->isActive()) abort(403, 'Your account is suspended.');
        if ($role !== 'admin' && $user->role !== $role) abort(403, "Requires '{$role}' role.");
        if ($role === 'admin' && ! $user->isAdmin()) abort(403, 'Admin access required.');
    }
}
