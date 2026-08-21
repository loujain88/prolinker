<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GET    /api/admin/users          — All users (search, role, status filter)
 * GET    /api/admin/users/{id}     — Single user detail
 * PUT    /api/admin/users/{id}/block   — Suspend + revoke tokens
 * PUT    /api/admin/users/{id}/unblock — Restore account
 * DELETE /api/admin/users/{id}     — Soft-delete (guards against active orders)
 */
class AdminUserController extends Controller
{
    /**
     * GET /api/admin/account-requests
     * Users whose signup is still awaiting admin review.
     */
    public function pendingApprovals(): JsonResponse
    {
        $users = User::where('approval_status', 'pending')
            ->where('role', '!=', 'admin')
            ->with(['client', 'seller'])
            ->latest()
            ->get();

        return response()->json(['data' => $users->map(fn($u) => $this->format($u, true))]);
    }

    /**
     * GET /api/admin/account-requests/{id}/id-document
     * Signed URL to view the uploaded ID/passport photo before deciding.
     */
    public function idDocument(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        if (! $user->id_document_path || ! \Illuminate\Support\Facades\Storage::disk('private')->exists($user->id_document_path)) {
            return response()->json(['message' => 'لا توجد وثيقة هوية مرفقة.'], 404);
        }

        return response()->json([
            'url' => app(\App\Services\WalletService::class)->temporaryReceiptUrl($user->id_document_path),
            'expires_in' => '15 minutes',
        ]);
    }

    /**
     * PUT /api/admin/account-requests/{id}/approve
     */
    public function approveAccount(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        if ($user->approval_status !== 'pending') {
            return response()->json(['message' => 'تم البت في هذا الطلب مسبقاً.'], 422);
        }

        $user->update(['approval_status' => 'approved', 'approved_at' => now()]);

        \App\Models\Notification::create([
            'user_id' => $user->id, 'type' => 'account.approved',
            'content' => 'تمت الموافقة على حسابك! تقدر تسجل الدخول الآن.',
            'action_url' => '/login',
        ]);

        return response()->json(['message' => 'تمت الموافقة على الحساب.']);
    }

    /**
     * PUT /api/admin/account-requests/{id}/reject
     */
    public function rejectAccount(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $user = User::findOrFail($id);
        if ($user->approval_status !== 'pending') {
            return response()->json(['message' => 'تم البت في هذا الطلب مسبقاً.'], 422);
        }

        $user->update(['approval_status' => 'rejected', 'approval_notes' => $validated['reason']]);

        \App\Models\Notification::create([
            'user_id' => $user->id, 'type' => 'account.rejected',
            'content' => "للأسف تم رفض طلب إنشاء حسابك. السبب: {$validated['reason']}",
            'action_url' => '/help',
        ]);

        return response()->json(['message' => 'تم رفض الطلب.']);
    }

    public function index(Request $request): JsonResponse
    {
        $users = User::with(['client', 'seller'])
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($i) => $i->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            )
            ->when($request->role, fn($q, $r) => $q->where('role', $r))
            ->when($request->has('is_active'), fn($q) =>
                $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return response()->json([
            'data' => collect($users->items())->map(fn($u) => $this->format($u)),
            'meta' => ['total' => $users->total(), 'current_page' => $users->currentPage(), 'last_page' => $users->lastPage()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with(['client', 'seller'])->findOrFail($id);
        return response()->json(['data' => $this->format($user, true)]);
    }

    public function block(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === Auth::id())      return response()->json(['message' => 'لا يمكنك حظر حسابك الخاص.'], 422);
        if ($user->role === 'admin')        return response()->json(['message' => 'لا يمكن حظر حسابات المسؤولين.'], 422);

        $user->update(['is_active' => false]);
        $user->tokens()->delete(); // Force logout immediately

        return response()->json(['message' => "تم حظر «{$user->name}» بنجاح.", 'data' => $this->format($user->fresh())]);
    }

    public function unblock(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);
        return response()->json(['message' => "تم رفع الحظر عن «{$user->name}».", 'data' => $this->format($user->fresh())]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) return response()->json(['message' => 'لا يمكنك حذف حسابك الخاص.'], 422);
        if ($user->role === 'admin')   return response()->json(['message' => 'لا يمكن حذف حسابات المسؤولين من هنا.'], 422);

        // Guard: active orders block deletion
        $hasActive = false;
        if ($user->role === 'client' && $user->client) {
            $hasActive = $user->client->requests()->whereIn('status', ['pending','in_progress','delivered'])->exists();
        }
        if ($user->role === 'seller' && $user->seller) {
            $hasActive = $user->seller->requests()->whereIn('status', ['pending','in_progress','delivered'])->exists();
        }
        if ($hasActive) {
            return response()->json(['message' => 'لا يمكن حذف هذا المستخدم — لديه طلبات نشطة.'], 422);
        }

        $name = $user->name;
        $user->tokens()->delete();
        $user->delete(); // soft delete preserves financial audit trail

        return response()->json(['message' => "تم حذف «{$name}» نهائياً."]);
    }

    private function format(User $u, bool $detailed = false): array
    {
        $data = [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
            'role' => $u->role, 'is_active' => $u->is_active,
            'phone' => $u->phone,
            'approval_status' => $u->approval_status,
            'approval_notes'  => $u->approval_notes,
            'has_id_document' => (bool) $u->id_document_path,
            'created_at' => $u->created_at->toIso8601String(),
        ];

        if ($u->role === 'client' && $u->client) {
            $data['profile'] = ['wallet_balance' => $u->client->wallet_balance, 'country' => $u->client->country, 'company_name' => $u->client->company_name];
        }
        if ($u->role === 'seller' && $u->seller) {
            $data['profile'] = [
                'wallet_balance' => $u->seller->wallet_balance,
                'average_rating' => $u->seller->average_rating,
                'total_orders_completed' => $u->seller->total_orders_completed,
                'is_verified' => $u->seller->is_verified,
                'country' => $u->seller->country,
            ];
        }

        return $data;
    }
}
