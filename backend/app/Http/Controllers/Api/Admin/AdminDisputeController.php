<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Dispute;
use App\Models\Notification;
use App\Models\Seller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/admin/disputes                       — All disputes (filterable)
 * GET /api/admin/disputes/{id}                  — Single dispute full context
 * PUT /api/admin/disputes/{id}/assign           — Admin assigns themselves
 * PUT /api/admin/disputes/{id}/resolve/client   — Refund escrow to client
 * PUT /api/admin/disputes/{id}/resolve/seller   — Release escrow to seller
 * PUT /api/admin/disputes/{id}/close            — Close with no action
 */
class AdminDisputeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $disputes = Dispute::with(['request.client.user', 'request.seller.user', 'request.service', 'request.conversation', 'admin'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($disputes->items())->map(fn($d) => $this->format($d, true)),
            'meta' => ['total' => $disputes->total(), 'current_page' => $disputes->currentPage(), 'last_page' => $disputes->lastPage()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $dispute = Dispute::with(['request.client.user', 'request.seller.user', 'request.service', 'request.conversation', 'admin'])->findOrFail($id);
        return response()->json(['data' => $this->format($dispute, true)]);
    }

    public function assign(int $id): JsonResponse
    {
        $dispute = Dispute::findOrFail($id);
        if ($dispute->isResolved()) return response()->json(['message' => 'النزاع محسوم بالفعل.'], 422);

        $dispute->update(['admin_id' => Auth::id(), 'status' => 'under_review', 'assigned_at' => now()]);
        return response()->json(['message' => 'تم تعيينك مسؤولاً عن هذا النزاع.', 'data' => $this->format($dispute->fresh('admin'))]);
    }

    /**
     * Resolves in the CLIENT's favor — meaning the SELLER was at fault.
     * Client gets 95% of the escrowed amount back; seller still gets a 5%
     * consolation share (covers partial effort, keeps goodwill).
     */
    public function resolveForClient(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['admin_notes' => ['required', 'string', 'min:10', 'max:3000']]);
        $dispute   = Dispute::with('request.client.user', 'request.seller.user')->findOrFail($id);
        if ($dispute->isResolved()) return response()->json(['message' => 'النزاع محسوم بالفعل.'], 422);

        DB::transaction(function () use ($dispute, $validated) {
            $order = $dispute->request;
            $total = (float) $order->escrow_amount;

            $sellerShare = round($total * 0.05, 2); // consolation, seller was at fault
            $clientShare = round($total - $sellerShare, 2); // 95%

            $client = Client::where('id', $order->client_id)->lockForUpdate()->firstOrFail();
            $client->increment('wallet_balance', $clientShare);

            $seller = Seller::where('id', $order->seller_id)->lockForUpdate()->firstOrFail();
            $seller->increment('wallet_balance', $sellerShare);

            $order->update(['status' => 'refunded', 'escrow_amount' => 0]);
            $dispute->update(['status' => 'resolved_client', 'admin_notes' => $validated['admin_notes'], 'resolved_at' => now()]);

            Notification::create(['user_id' => $order->client->user_id, 'type' => 'dispute.resolved',
                'content' => "تم البت في النزاع لصالحك. تم إعادة \${$clientShare} (95%) إلى محفظتك.", 'action_url' => '/client/wallet']);
            Notification::create(['user_id' => $order->seller->user_id, 'type' => 'dispute.resolved',
                'content' => "تم البت في النزاع لصالح العميل. تم إضافة \${$sellerShare} (5% تعويض) إلى محفظتك. قرار الإدارة: {$validated['admin_notes']}", 'action_url' => '/seller/wallet']);
        });

        return response()->json(['message' => 'تم حل النزاع — 95% للعميل و5% تعويض للمستقل.']);
    }

    /**
     * Resolves in the SELLER's favor — meaning the CLIENT was at fault.
     * Seller gets 95% of the escrowed amount; client still gets a 5%
     * consolation refund.
     */
    public function resolveForSeller(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['admin_notes' => ['required', 'string', 'min:10', 'max:3000']]);
        $dispute   = Dispute::with('request.seller.user', 'request.client.user')->findOrFail($id);
        if ($dispute->isResolved()) return response()->json(['message' => 'النزاع محسوم بالفعل.'], 422);

        DB::transaction(function () use ($dispute, $validated) {
            $order = $dispute->request;
            $total = (float) $order->escrow_amount;

            $clientShare = round($total * 0.05, 2); // consolation, client was at fault
            $sellerShare = round($total - $clientShare, 2); // 95%

            $seller = Seller::where('id', $order->seller_id)->lockForUpdate()->firstOrFail();
            $seller->increment('wallet_balance', $sellerShare);

            $client = Client::where('id', $order->client_id)->lockForUpdate()->firstOrFail();
            $client->increment('wallet_balance', $clientShare);

            $order->update(['status' => 'completed', 'escrow_amount' => 0, 'completed_at' => now()]);
            $dispute->update(['status' => 'resolved_seller', 'admin_notes' => $validated['admin_notes'], 'resolved_at' => now()]);

            Notification::create(['user_id' => $order->seller->user_id, 'type' => 'dispute.resolved',
                'content' => "تم البت في النزاع لصالحك. تم إضافة \${$sellerShare} (95%) إلى محفظتك.", 'action_url' => '/seller/wallet']);
            Notification::create(['user_id' => $order->client->user_id, 'type' => 'dispute.resolved',
                'content' => "تم البت في النزاع لصالح المستقل. تم رد \${$clientShare} (5% تعويض) إلى محفظتك. قرار الإدارة: {$validated['admin_notes']}", 'action_url' => '/client/wallet']);
        });

        return response()->json(['message' => 'تم حل النزاع — 95% للمستقل و5% تعويض للعميل.']);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['admin_notes' => ['required', 'string', 'min:10', 'max:3000']]);
        $dispute   = Dispute::findOrFail($id);
        if ($dispute->isResolved()) return response()->json(['message' => 'النزاع محسوم بالفعل.'], 422);

        $dispute->update(['status' => 'closed_no_action', 'admin_notes' => $validated['admin_notes'], 'resolved_at' => now()]);
        return response()->json(['message' => 'تم إغلاق النزاع بدون إجراء مالي.']);
    }

    private function format(Dispute $d, bool $detailed = false): array
    {
        $data = [
            'id' => $d->id, 'request_id' => $d->request_id, 'opened_by' => $d->opened_by,
            'status' => $d->status, 'reason' => $d->reason, 'admin_notes' => $d->admin_notes,
            'assigned_at' => $d->assigned_at?->toIso8601String(),
            'resolved_at' => $d->resolved_at?->toIso8601String(),
            'created_at'  => $d->created_at->toIso8601String(),
            'admin' => $d->relationLoaded('admin') ? $d->admin?->name : null,
        ];

        if ($detailed && $d->relationLoaded('request')) {
            $order = $d->request;
            $data['order'] = [
                'id' => $order->id, 'final_price' => $order->final_price,
                'escrow_amount' => $order->escrow_amount, 'status' => $order->status,
                'service' => $order->service?->title,
                'client' => ['id' => $order->client->id, 'name' => $order->client->user->name, 'email' => $order->client->user->email],
                'seller' => ['id' => $order->seller->id, 'name' => $order->seller->user->name, 'email' => $order->seller->user->email],
                // Timeline — when was it requested vs. delivered, so the admin
                // can judge whether the seller met the agreed deadline.
                'requested_at' => $order->created_at->toIso8601String(),
                'deadline_at'  => $order->deadline_at?->toIso8601String(),
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'delivery_files' => $order->delivery_files ?? [],
                // Direct link to the client↔seller conversation for this order,
                // so the admin can read the full back-and-forth (messages +
                // attachments) to judge who's actually at fault.
                'conversation_id' => $order->conversation?->id,
            ];
        }

        return $data;
    }
}
