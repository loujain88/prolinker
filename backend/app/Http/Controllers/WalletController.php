<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * WalletController
 *
 * Handles all deposit and withdrawal operations.
 *
 * Authorization model:
 *   - Deposit endpoints:    role = client
 *   - Withdrawal endpoints: role = seller
 *   - Approve/Reject:       role = admin
 *
 * File uploads are stored in the 'private' disk (never public).
 * Temporary signed URLs are generated on-demand for viewing receipts.
 */
class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService
    ) {}

    // =========================================================================
    // CLIENT — DEPOSITS
    // =========================================================================

    /**
     * POST /api/v1/wallet/deposit
     *
     * Client submits a manual bank-transfer deposit request.
     * MUST include a receipt image as proof of payment.
     *
     * Body (multipart/form-data):
     *   - amount           float    required
     *   - payment_method   string   required (e.g. 'bank_transfer')
     *   - transaction_receipt  file required (jpeg|png|pdf, max 5 MB)
     */
    public function deposit(Request $request): JsonResponse
    {
        $this->authorize('client');

        $validated = $request->validate([
            'amount'               => ['required', 'numeric', 'min:1', 'max:50000'],
            'payment_method'       => ['required', 'string', 'in:bank_transfer,cash'],
            'transaction_receipt'  => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);

        $deposit = $this->walletService->createDeposit(
            user:          Auth::user(),
            amount:        (float) $validated['amount'],
            receiptFile:   $request->file('transaction_receipt'),
            paymentMethod: $validated['payment_method'],
        );

        return response()->json([
            'message' => 'Deposit request submitted. You will be notified once approved.',
            'data'    => $this->formatDeposit($deposit),
        ], 201);
    }

    /**
     * GET /api/v1/wallet/deposits
     *
     * List all deposits for the authenticated client.
     */
    public function myDeposits(Request $request): JsonResponse
    {
        $this->authorize('client');

        $deposits = Auth::user()->deposits()
            ->latest()
            ->paginate(15);

        return response()->json(['data' => $deposits]);
    }

    /**
     * GET /api/v1/wallet/deposits/{id}/receipt
     *
     * Generate a short-lived signed URL for the client to view their receipt.
     */
    public function viewDepositReceipt(int $id): JsonResponse
    {
        $deposit = Deposit::findOrFail($id);

        // Ensure the authenticated user owns this deposit
        if ($deposit->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $deposit->transaction_receipt) {
            return response()->json(['message' => 'No receipt on file.'], 404);
        }

        return response()->json([
            'url'        => $this->walletService->temporaryReceiptUrl($deposit->transaction_receipt),
            'expires_in' => '15 minutes',
        ]);
    }

    // =========================================================================
    // SELLER — WITHDRAWALS
    // =========================================================================

    /**
     * POST /api/v1/wallet/withdraw
     *
     * Seller submits a withdrawal request.
     * Funds are immediately reserved from the seller's wallet.
     *
     * Body (multipart/form-data):
     *   - amount         float  required
     *   - payout_image   file   required (a photo showing where to send the
     *                           payout — e.g. a Sham Cash QR code, or written
     *                           delivery/hand-off instructions)
     *   - notes          string optional
     */
    public function withdraw(Request $request): JsonResponse
    {
        $this->authorize('seller');

        $validated = $request->validate([
            'amount'       => ['required', 'numeric', 'min:10', 'max:50000'],
            'payout_image' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $imagePath = $request->file('payout_image')->store('withdrawals/' . Auth::id(), 'private');

        $withdrawal = $this->walletService->createWithdrawal(
            user:          Auth::user(),
            amount:        (float) $validated['amount'],
            payoutMethod:  'qr_photo',
            payoutDetails: ['payout_image_path' => $imagePath],
            notes:         $validated['notes'] ?? '',
        );

        return response()->json([
            'message' => 'Withdrawal request submitted. You will be notified once processed.',
            'data'    => $this->formatWithdrawal($withdrawal),
        ], 201);
    }

    /**
     * GET /api/v1/wallet/withdrawals
     *
     * List all withdrawals for the authenticated seller.
     */
    public function myWithdrawals(Request $request): JsonResponse
    {
        $this->authorize('seller');

        $withdrawals = Auth::user()->withdrawals()
            ->latest()
            ->paginate(15);

        return response()->json(['data' => $withdrawals]);
    }

    /**
     * GET /api/v1/wallet/withdrawals/{id}/receipt
     *
     * Seller retrieves the payout receipt uploaded by admin as proof of payment.
     */
    public function viewWithdrawalReceipt(int $id): JsonResponse
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if ($withdrawal->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $withdrawal->isApproved() || ! $withdrawal->transaction_receipt) {
            return response()->json(['message' => 'Receipt not available yet.'], 404);
        }

        return response()->json([
            'url'        => $this->walletService->temporaryReceiptUrl($withdrawal->transaction_receipt),
            'expires_in' => '15 minutes',
        ]);
    }

    // =========================================================================
    // ADMIN — DEPOSIT MANAGEMENT
    // =========================================================================

    /**
     * GET /api/v1/admin/deposits
     *
     * List all deposits (admin only), filterable by status.
     */
    public function adminListDeposits(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $deposits = Deposit::with('user')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $deposits]);
    }

    /**
     * GET /api/v1/admin/deposits/{id}/receipt
     *
     * Admin views client's deposit receipt for review.
     */
    public function adminViewDepositReceipt(int $id): JsonResponse
    {
        $this->authorize('admin');

        $deposit = Deposit::findOrFail($id);

        if (! $deposit->transaction_receipt) {
            return response()->json(['message' => 'No receipt on file.'], 404);
        }

        return response()->json([
            'url'        => $this->walletService->temporaryReceiptUrl($deposit->transaction_receipt),
            'expires_in' => '15 minutes',
        ]);
    }

    /**
     * PATCH /api/v1/admin/deposits/{id}/approve
     *
     * Admin approves a deposit → client wallet credited.
     *
     * Body (JSON):
     *   - notes  string  optional
     */
    public function approveDeposit(Request $request, int $id): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $deposit = Deposit::findOrFail($id);
        $deposit = $this->walletService->approveDeposit(
            deposit: $deposit,
            admin:   Auth::user(),
            notes:   $validated['notes'] ?? '',
        );

        return response()->json([
            'message' => "Deposit #{$id} approved. Client wallet credited \${$deposit->amount}.",
            'data'    => $this->formatDeposit($deposit),
        ]);
    }

    /**
     * PATCH /api/v1/admin/deposits/{id}/reject
     *
     * Admin rejects a deposit.
     *
     * Body (JSON):
     *   - notes  string  required (admin must state reason)
     */
    public function rejectDeposit(Request $request, int $id): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $deposit = Deposit::findOrFail($id);
        $deposit = $this->walletService->rejectDeposit(
            deposit: $deposit,
            admin:   Auth::user(),
            notes:   $validated['notes'],
        );

        return response()->json([
            'message' => "Deposit #{$id} rejected.",
            'data'    => $this->formatDeposit($deposit),
        ]);
    }

    // =========================================================================
    // ADMIN — WITHDRAWAL MANAGEMENT
    // =========================================================================

    /**
     * GET /api/v1/admin/withdrawals
     *
     * List all withdrawals (admin only), filterable by status.
     */
    public function adminListWithdrawals(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $withdrawals = Withdrawal::with('user')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $withdrawals]);
    }

    /**
     * PATCH /api/v1/admin/withdrawals/{id}/approve
     *
     * Admin approves a withdrawal and MUST upload a payout receipt.
     *
     * Body (multipart/form-data):
     *   - transaction_receipt  file    required (jpeg|png|pdf, max 5 MB)
     *   - notes                string  optional
     */
    public function approveWithdrawal(Request $request, int $id): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'transaction_receipt' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ]);

        $withdrawal = Withdrawal::findOrFail($id);
        $withdrawal = $this->walletService->approveWithdrawal(
            withdrawal:  $withdrawal,
            admin:       Auth::user(),
            receiptFile: $request->file('transaction_receipt'),
            notes:       $validated['notes'] ?? '',
        );

        return response()->json([
            'message' => "Withdrawal #{$id} approved. Payout receipt uploaded.",
            'data'    => $this->formatWithdrawal($withdrawal),
        ]);
    }

    /**
     * PATCH /api/v1/admin/withdrawals/{id}/reject
     *
     * Admin rejects a withdrawal → reserved funds restored to seller.
     *
     * Body (JSON):
     *   - notes  string  required
     */
    public function rejectWithdrawal(Request $request, int $id): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $withdrawal = Withdrawal::findOrFail($id);
        $withdrawal = $this->walletService->rejectWithdrawal(
            withdrawal: $withdrawal,
            admin:      Auth::user(),
            notes:      $validated['notes'],
        );

        return response()->json([
            'message' => "Withdrawal #{$id} rejected. Seller balance restored.",
            'data'    => $this->formatWithdrawal($withdrawal),
        ]);
    }

    // =========================================================================
    // SHARED WALLET SUMMARY
    // =========================================================================

    /**
     * GET /api/v1/wallet/summary
     *
     * Returns the authenticated user's wallet balance and recent transactions.
     */
    public function summary(): JsonResponse
    {
        $user = Auth::user();

        if ($user->isClient()) {
            $profile = $user->client;
            return response()->json([
                'role'            => 'client',
                'wallet_balance'  => $profile->wallet_balance,
                'recent_deposits' => $user->deposits()->latest()->take(5)->get()
                    ->map(fn($d) => $this->formatDeposit($d)),
            ]);
        }

        if ($user->isSeller()) {
            $profile = $user->seller;
            return response()->json([
                'role'                => 'seller',
                'wallet_balance'      => $profile->wallet_balance,
                'recent_withdrawals'  => $user->withdrawals()->latest()->take(5)->get()
                    ->map(fn($w) => $this->formatWithdrawal($w)),
            ]);
        }

        return response()->json(['message' => 'Admins do not have a personal wallet.'], 403);
    }

    // =========================================================================
    // PRIVATE FORMATTERS
    // =========================================================================

    private function formatDeposit(Deposit $d): array
    {
        return [
            'id'             => $d->id,
            'amount'         => $d->amount,
            'currency'       => $d->currency,
            'status'         => $d->status,
            'payment_method' => $d->payment_method,
            'has_receipt'    => (bool) $d->transaction_receipt,
            'admin_notes'    => $d->admin_notes,
            'approved_at'    => $d->approved_at?->toIso8601String(),
            'rejected_at'    => $d->rejected_at?->toIso8601String(),
            'created_at'     => $d->created_at->toIso8601String(),
        ];
    }

    private function formatWithdrawal(Withdrawal $w): array
    {
        return [
            'id'             => $w->id,
            'amount'         => $w->amount,
            'currency'       => $w->currency,
            'status'         => $w->status,
            'payout_method'  => $w->payout_method,
            'has_payout_image' => (bool) ($w->payout_details['payout_image_path'] ?? null),
            'has_receipt'    => (bool) $w->transaction_receipt,
            'admin_notes'    => $w->admin_notes,
            'seller_notes'   => $w->seller_notes,
            'approved_at'    => $w->approved_at?->toIso8601String(),
            'rejected_at'    => $w->rejected_at?->toIso8601String(),
            'created_at'     => $w->created_at->toIso8601String(),
        ];
    }

    // ── Role gate helper ──────────────────────────────────────────────────────

    private function authorize(string $role): void
    {
        $user = Auth::user();

        if (! $user || ! $user->isActive()) {
            abort(403, 'Your account is suspended.');
        }

        if ($role !== 'admin' && $user->role !== $role) {
            abort(403, "This action requires the '{$role}' role.");
        }

        if ($role === 'admin' && ! $user->isAdmin()) {
            abort(403, 'Admin access required.');
        }
    }
}
