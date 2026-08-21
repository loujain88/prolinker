<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\ServiceRequest;
use App\Services\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * DisputeController
 *
 * Manages the dispute lifecycle:
 *   Client opens dispute → Admin assigns → Admin resolves
 *
 * Resolution outcomes:
 *   - resolved_client : full refund to client (escrow returned)
 *   - resolved_seller : payment released to seller (escrow released)
 *   - closed_no_action: no financial change (dispute closed in error)
 *
 * AI Hook: After resolveForClient/resolveForSeller, call your AI analysis
 * model and store the result in dispute.ai_recommended_resolution.
 * Surface it only in the admin detail view.
 */
class DisputeController extends Controller
{
    public function __construct(
        private readonly DisputeService $disputeService
    ) {}

    // =========================================================================
    // CLIENT ACTIONS
    // =========================================================================

    /**
     * POST /api/v1/orders/{orderId}/dispute
     *
     * Client opens a dispute on a delivered order.
     *
     * Body (multipart/form-data):
     *   - reason      string   required  (min 20 chars)
     *   - evidence[]  file[]   optional  (up to 5 files: jpeg|png|pdf, max 5 MB each)
     */
    public function open(Request $request, int $orderId): JsonResponse
    {
        $this->gate('client');

        $validated = $request->validate([
            'reason'      => ['required', 'string', 'min:20', 'max:3000'],
            'evidence'    => ['nullable', 'array', 'max:5'],
            'evidence.*'  => ['file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);

        $order = ServiceRequest::with(['client.user', 'service'])
            ->findOrFail($orderId);

        $dispute = $this->disputeService->open(
            order:         $order,
            clientUser:    Auth::user(),
            reason:        $validated['reason'],
            evidenceFiles: $request->file('evidence') ?? [],
        );

        return response()->json([
            'message' => 'Dispute opened. An admin will review your case shortly.',
            'data'    => $this->formatDispute($dispute->load('request.service')),
        ], 201);
    }

    /**
     * GET /api/v1/client/disputes
     *
     * Client views all their disputes.
     */
    public function myDisputes(): JsonResponse
    {
        $this->gate('client');

        $client = Auth::user()->client;

        $disputes = Dispute::whereHas('request', fn($q) => $q->where('client_id', $client->id))
            ->with(['request.service', 'admin'])
            ->latest()
            ->paginate(10);

        return response()->json(['data' => $disputes]);
    }

    // =========================================================================
    // ADMIN ACTIONS
    // =========================================================================

    /**
     * GET /api/v1/admin/disputes
     *
     * List all disputes for admin dashboard.
     *
     * Query params:
     *   - status  string  Filter by status
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $this->gate('admin');

        $disputes = Dispute::with([
                'request.client.user',
                'request.seller.user',
                'request.service',
                'admin',
            ])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $disputes]);
    }

    /**
     * GET /api/v1/admin/disputes/{id}
     *
     * Admin views a single dispute with full context.
     */
    public function adminShow(int $id): JsonResponse
    {
        $this->gate('admin');

        $dispute = Dispute::with([
            'request.client.user',
            'request.seller.user',
            'request.service',
            'admin',
        ])->findOrFail($id);

        return response()->json(['data' => $this->formatDispute($dispute, detailed: true)]);
    }

    /**
     * PATCH /api/v1/admin/disputes/{id}/assign
     *
     * Admin assigns themselves as the mediator for this dispute.
     */
    public function assign(int $id): JsonResponse
    {
        $this->gate('admin');

        $dispute = Dispute::findOrFail($id);
        $dispute = $this->disputeService->assign($dispute, Auth::user());

        return response()->json([
            'message' => 'Dispute assigned to you. Status is now under_review.',
            'data'    => $this->formatDispute($dispute),
        ]);
    }

    /**
     * PATCH /api/v1/admin/disputes/{id}/resolve/client
     *
     * Resolve in client's favour → full refund issued.
     *
     * Body (JSON):
     *   - admin_notes  string  required
     */
    public function resolveForClient(Request $request, int $id): JsonResponse
    {
        $this->gate('admin');

        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $dispute = Dispute::with('request.client.user', 'request.seller.user')->findOrFail($id);
        $dispute = $this->disputeService->resolveForClient($dispute, Auth::user(), $validated['admin_notes']);

        return response()->json([
            'message' => 'Dispute resolved. Refund issued to client.',
            'data'    => $this->formatDispute($dispute),
        ]);
    }

    /**
     * PATCH /api/v1/admin/disputes/{id}/resolve/seller
     *
     * Resolve in seller's favour → escrow released to seller.
     *
     * Body (JSON):
     *   - admin_notes  string  required
     */
    public function resolveForSeller(Request $request, int $id): JsonResponse
    {
        $this->gate('admin');

        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $dispute = Dispute::with('request.client.user', 'request.seller.user')->findOrFail($id);
        $dispute = $this->disputeService->resolveForSeller($dispute, Auth::user(), $validated['admin_notes']);

        return response()->json([
            'message' => 'Dispute resolved. Funds released to seller.',
            'data'    => $this->formatDispute($dispute),
        ]);
    }

    /**
     * PATCH /api/v1/admin/disputes/{id}/close
     *
     * Close a dispute without any financial action.
     *
     * Body (JSON):
     *   - admin_notes  string  required
     */
    public function closeWithNoAction(Request $request, int $id): JsonResponse
    {
        $this->gate('admin');

        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $dispute = Dispute::findOrFail($id);
        $dispute = $this->disputeService->closeWithNoAction($dispute, Auth::user(), $validated['admin_notes']);

        return response()->json([
            'message' => 'Dispute closed with no action.',
            'data'    => $this->formatDispute($dispute),
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function formatDispute(Dispute $d, bool $detailed = false): array
    {
        $data = [
            'id'          => $d->id,
            'request_id'  => $d->request_id,
            'opened_by'   => $d->opened_by,
            'status'      => $d->status,
            'admin_notes' => $d->admin_notes,
            'assigned_at' => $d->assigned_at?->toIso8601String(),
            'resolved_at' => $d->resolved_at?->toIso8601String(),
            'created_at'  => $d->created_at->toIso8601String(),
            'admin'       => $d->relationLoaded('admin') ? $d->admin?->name : null,
        ];

        if ($detailed) {
            $data['reason']         = $d->reason;
            $data['evidence_count'] = count($d->evidence_paths ?? []);
            $data['order']          = $d->relationLoaded('request') ? [
                'id'          => $d->request->id,
                'final_price' => $d->request->final_price,
                'status'      => $d->request->status,
                'service'     => $d->request->service?->title,
                'client'      => $d->request->client?->user->name,
                'seller'      => $d->request->seller?->user->name,
            ] : null;
        }

        return $data;
    }

    private function gate(string $role): void
    {
        $user = Auth::user();
        if (! $user?->isActive()) abort(403, 'Your account is suspended.');
        if ($role !== 'admin' && $user->role !== $role) abort(403, "Requires '{$role}' role.");
        if ($role === 'admin' && ! $user->isAdmin()) abort(403, 'Admin access required.');
    }
}
