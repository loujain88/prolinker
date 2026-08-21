<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Seller;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET  /api/admin/withdrawals/pending      — Pending withdrawals with bank details
 * GET  /api/admin/withdrawals              — All withdrawals (filterable)
 * PUT  /api/admin/withdrawals/{id}/approve — Approve + upload payout receipt (multipart)
 * PUT  /api/admin/withdrawals/{id}/reject  — Reject → restore seller balance
 */
class AdminWithdrawalController extends Controller
{
    public function payoutImage(int $id): JsonResponse
    {
        $withdrawal = Withdrawal::findOrFail($id);
        $path = $withdrawal->payout_details['payout_image_path'] ?? null;

        if (! $path || ! \Illuminate\Support\Facades\Storage::disk('private')->exists($path)) {
            return response()->json(['message' => 'لا توجد صورة استلام مرفقة لهذا الطلب.'], 404);
        }

        return response()->json([
            'url'        => app(\App\Services\WalletService::class)->temporaryReceiptUrl($path),
            'expires_in' => '15 minutes',
        ]);
    }

    public function receipt(int $id): JsonResponse
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if (! $withdrawal->transaction_receipt) {
            return response()->json(['message' => 'لا يوجد إيصال مرفق لهذا الطلب.'], 404);
        }
        if (! \Illuminate\Support\Facades\Storage::disk('private')->exists($withdrawal->transaction_receipt)) {
            return response()->json(['message' => 'ملف الإيصال غير موجود في التخزين.'], 404);
        }

        return response()->json([
            'url'        => app(\App\Services\WalletService::class)->temporaryReceiptUrl($withdrawal->transaction_receipt),
            'expires_in' => '15 minutes',
        ]);
    }

    public function pending(): JsonResponse
    {
        $withdrawals = Withdrawal::with(['user.seller'])
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn($w) => $this->format($w));

        return response()->json(['data' => $withdrawals]);
    }

    public function index(Request $request): JsonResponse
    {
        $withdrawals = Withdrawal::with(['user.seller', 'processedBy'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) =>
                $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            )
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($withdrawals->items())->map(fn($w) => $this->format($w)),
            'meta' => ['total' => $withdrawals->total(), 'current_page' => $withdrawals->currentPage(), 'last_page' => $withdrawals->lastPage()],
        ]);
    }

    /**
     * Admin confirms payout was sent — must upload a receipt as proof.
     * Seller wallet was already debited when withdrawal was submitted,
     * so no wallet change is needed here.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $withdrawal = Withdrawal::with('user')->findOrFail($id);

        if (! $withdrawal->isPending()) {
            return response()->json(['message' => "الطلب #{$id} ليس في حالة معلق."], 422);
        }

        $validated = $request->validate([
            'transaction_receipt' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ]);

        $receiptPath = $request->file('transaction_receipt')
            ->store("receipts/withdrawals/{$withdrawal->user_id}", 'private');

        $withdrawal->update([
            'status'              => 'approved',
            'transaction_receipt' => $receiptPath,
            'admin_notes'         => $validated['notes'] ?? null,
            'approved_at'         => now(),
            'processed_by'        => Auth::id(),
        ]);

        Notification::create([
            'user_id'         => $withdrawal->user_id,
            'type'            => 'withdrawal.approved',
            'content'         => "تمت الموافقة على طلب سحب \${$withdrawal->amount}. يمكنك تحميل إيصال التحويل من صفحة المحفظة.",
            'action_url'      => '/seller/wallet',
            'notifiable_type' => Withdrawal::class,
            'notifiable_id'   => $withdrawal->id,
        ]);

        return response()->json([
            'message' => "تمت الموافقة على السحب #{$id} وتم رفع إيصال الدفع.",
            'data'    => $this->format($withdrawal->fresh('user.seller')),
        ]);
    }

    /**
     * Reject withdrawal — restore reserved amount to seller wallet.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $withdrawal = Withdrawal::with('user.seller')->findOrFail($id);

        if (! $withdrawal->isPending()) {
            return response()->json(['message' => "الطلب #{$id} ليس في حالة معلق."], 422);
        }

        $validated = $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($withdrawal, $validated) {
            $seller = Seller::where('user_id', $withdrawal->user_id)->lockForUpdate()->firstOrFail();
            $seller->increment('wallet_balance', $withdrawal->amount);

            $withdrawal->update([
                'status'       => 'rejected',
                'admin_notes'  => $validated['notes'],
                'rejected_at'  => now(),
                'processed_by' => Auth::id(),
            ]);

            Notification::create([
                'user_id'         => $withdrawal->user_id,
                'type'            => 'withdrawal.rejected',
                'content'         => "تم رفض طلب سحب \${$withdrawal->amount}. السبب: {$validated['notes']} — تم إعادة المبلغ إلى محفظتك.",
                'action_url'      => '/seller/wallet',
                'notifiable_type' => Withdrawal::class,
                'notifiable_id'   => $withdrawal->id,
            ]);
        });

        return response()->json([
            'message' => "تم رفض السحب #{$id} وإعادة المبلغ إلى محفظة البائع.",
            'data'    => $this->format($withdrawal->fresh('user.seller')),
        ]);
    }

    private function format(Withdrawal $w): array
    {
        return [
            'id' => $w->id, 'amount' => $w->amount, 'currency' => $w->currency,
            'status' => $w->status, 'payout_method' => $w->payout_method,
            'payout_details' => $w->payout_details,
            'has_payout_image' => (bool) ($w->payout_details['payout_image_path'] ?? null),
            'has_receipt' => (bool) $w->transaction_receipt,
            'admin_notes' => $w->admin_notes, 'seller_notes' => $w->seller_notes,
            'approved_at' => $w->approved_at?->toIso8601String(),
            'rejected_at' => $w->rejected_at?->toIso8601String(),
            'created_at'  => $w->created_at->toIso8601String(),
            'user' => $w->relationLoaded('user') ? [
                'id' => $w->user->id, 'name' => $w->user->name, 'email' => $w->user->email,
                'wallet_balance' => $w->user->seller?->wallet_balance ?? 0,
            ] : null,
            'processed_by' => $w->processedBy?->name ?? null,
        ];
    }
}
