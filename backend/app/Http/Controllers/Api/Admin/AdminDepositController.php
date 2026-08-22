<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * GET  /api/admin/deposits/pending     — Pending deposits + receipt URL hook
 * GET  /api/admin/deposits             — All deposits (filterable)
 * GET  /api/admin/deposits/{id}/receipt — Signed URL for receipt image
 * PUT  /api/admin/deposits/{id}/approve — Approve → credit client wallet
 * PUT  /api/admin/deposits/{id}/reject  — Reject (no wallet change)
 */
class AdminDepositController extends Controller
{
    public function pending(): JsonResponse
    {
        $deposits = Deposit::with(['user.client'])
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn($d) => $this->format($d));

        return response()->json(['data' => $deposits]);
    }

    public function index(Request $request): JsonResponse
    {
        $deposits = Deposit::with(['user.client', 'processedBy'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) =>
                $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            )
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($deposits->items())->map(fn($d) => $this->format($d)),
            'meta' => ['total' => $deposits->total(), 'current_page' => $deposits->currentPage(), 'last_page' => $deposits->lastPage()],
        ]);
    }

    public function receipt(int $id): JsonResponse
    {
        $deposit = Deposit::findOrFail($id);

        if (! $deposit->transaction_receipt) {
            return response()->json(['message' => 'لا يوجد إيصال مرفق لهذا الطلب.'], 404);
        }
        if (! Storage::disk('private')->exists($deposit->transaction_receipt)) {
            return response()->json(['message' => 'ملف الإيصال غير موجود في التخزين.'], 404);
        }

        return response()->json([
            'url'        => app(\App\Services\WalletService::class)->temporaryReceiptUrl($deposit->transaction_receipt),
            'expires_in' => '15 minutes',
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $deposit = Deposit::with('user.client')->findOrFail($id);

        if (! $deposit->isPending()) {
            return response()->json(['message' => "الطلب #{$id} ليس في حالة معلق."], 422);
        }

        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        DB::transaction(function () use ($deposit, $validated) {
            $client = Client::where('user_id', $deposit->user_id)->lockForUpdate()->firstOrFail();
            $client->increment('wallet_balance', $deposit->amount);

            $deposit->update([
                'status'       => 'approved',
                'admin_notes'  => $validated['notes'] ?? null,
                'approved_at'  => now(),
                'processed_by' => Auth::id(),
            ]);

            Notification::create([
                'user_id'         => $deposit->user_id,
                'type'            => 'deposit.approved',
                'content'         => "تمت الموافقة على طلب الشحن بمبلغ \${$deposit->amount}. تم إضافة المبلغ إلى محفظتك.",
                'action_url'      => '/client/wallet',
                'notifiable_type' => Deposit::class,
                'notifiable_id'   => $deposit->id,
            ]);
        });

        return response()->json([
            'message' => "تمت الموافقة على الإيداع #{$id} — تم تحديث محفظة العميل.",
            'data'    => $this->format($deposit->fresh('user.client')),
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $deposit = Deposit::with('user')->findOrFail($id);

        if (! $deposit->isPending()) {
            return response()->json(['message' => "الطلب #{$id} ليس في حالة معلق."], 422);
        }

        $validated = $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        $deposit->update([
            'status'       => 'rejected',
            'admin_notes'  => $validated['notes'],
            'rejected_at'  => now(),
            'processed_by' => Auth::id(),
        ]);

        Notification::create([
            'user_id'         => $deposit->user_id,
            'type'            => 'deposit.rejected',
            'content'         => "تم رفض طلب الشحن بمبلغ \${$deposit->amount}. السبب: {$validated['notes']}",
            'action_url'      => '/client/wallet',
            'notifiable_type' => Deposit::class,
            'notifiable_id'   => $deposit->id,
        ]);

        return response()->json(['message' => "تم رفض الإيداع #{$id}.", 'data' => $this->format($deposit->fresh())]);
    }

    private function format(Deposit $d): array
    {
        return [
            'id' => $d->id, 'amount' => $d->amount, 'currency' => $d->currency,
            'status' => $d->status, 'payment_method' => $d->payment_method,
            'has_receipt' => (bool) $d->transaction_receipt,
            'admin_notes' => $d->admin_notes,
            'approved_at' => $d->approved_at?->toIso8601String(),
            'rejected_at' => $d->rejected_at?->toIso8601String(),
            'created_at'  => $d->created_at->toIso8601String(),
            'user' => $d->relationLoaded('user') ? [
                'id' => $d->user->id, 'name' => $d->user->name, 'email' => $d->user->email,
                'wallet_balance' => $d->user->client?->wallet_balance ?? 0,
            ] : null,
            'processed_by' => $d->processedBy?->name ?? null,
        ];
    }
}
