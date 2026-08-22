<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * AuthController
 *
 * Handles registration, login, and logout via Laravel Sanctum tokens.
 * On registration, the role-specific profile (Client or Seller) is
 * created atomically in the same transaction as the User record.
 */
class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/register
     *
     * Body (JSON):
     *   - name      string   required
     *   - email     string   required
     *   - password  string   required (min 8, mixed case, numbers)
     *   - role      string   required ('client' | 'seller')
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'unique:users,email'],
            'password'     => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'phone'        => ['required', 'string', 'max:30', 'unique:users,phone'],
            'id_document'  => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'role'     => ['required', 'in:client,seller'],
            // Cold-start signal for recommendations — which service categories
            // is a new client typically interested in?
            'preferred_category_ids'   => ['nullable', 'array', 'max:8'],
            'preferred_category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $user = DB::transaction(function () use ($validated, $request): User {
            $idDocumentPath = $request->file('id_document')->store('id_documents', 'private');

            $user = User::create([
                'name'             => $validated['name'],
                'email'            => $validated['email'],
                'password'         => Hash::make($validated['password']),
                'role'             => $validated['role'],
                'phone'            => $validated['phone'],
                'id_document_path' => $idDocumentPath,
                'is_active'        => true,
            ]);

            // Auto-create the role-specific profile
            if ($validated['role'] === 'client') {
                Client::create([
                    'user_id' => $user->id,
                    'wallet_balance' => 0,
                    'preferred_category_ids' => $validated['preferred_category_ids'] ?? [],
                ]);
            } else {
                Seller::create(['user_id' => $user->id, 'wallet_balance' => 0]);
            }

            return $user;
        });

        // Notify admins — they need to review and approve/reject this signup.
        $admins = User::where('role', 'admin')->where('is_active', true)->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::create([
                'user_id'    => $admin->id,
                'type'       => 'account.pending_approval',
                'content'    => "طلب تسجيل حساب جديد ({$user->role}) من {$user->name} بانتظار المراجعة.",
                'action_url' => '/admin/account-requests',
            ]);
        }

        return response()->json([
            'message' => 'تم تقديم طلب إنشاء حساب، راجع بريدك الإلكتروني خلال مدة أقصاها 5 أيام.',
            'user'    => $this->formatUser($user),
        ], 201);
    }

    /**
     * POST /api/v1/auth/login
     *
     * Body (JSON):
     *   - email     string  required
     *   - password  string  required
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($validated)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            return response()->json(['message' => 'Your account has been suspended.'], 403);
        }

        if ($user->role !== 'admin' && $user->approval_status === 'pending') {
            Auth::logout();
            return response()->json(['message' => 'حسابك لسا قيد المراجعة من الإدارة. رح توصلك رسالة عبر البريد خلال 5 أيام كحد أقصى.'], 403);
        }
        if ($user->role !== 'admin' && $user->approval_status === 'rejected') {
            Auth::logout();
            return response()->json(['message' => 'للأسف تم رفض طلب إنشاء حسابك. تواصل مع الإدارة لمزيد من التفاصيل.'], 403);
        }

        // Revoke all old tokens on login (single-session policy)
        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/v1/auth/me
     *
     * Returns the authenticated user with their role profile and wallet balance.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = match ($user->role) {
            'client' => $user->client,
            'seller' => $user->seller,
            default  => null,
        };

        return response()->json([
            'user'    => $this->formatUser($user),
            'profile' => $profile,
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'is_active'  => $user->is_active,
            'is_super_admin' => $user->is_super_admin,
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }
}
