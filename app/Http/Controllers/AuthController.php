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
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'role'     => ['required', 'in:client,seller'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
                'role'      => $validated['role'],
                'is_active' => true,
            ]);

            // Auto-create the role-specific profile
            if ($validated['role'] === 'client') {
                Client::create(['user_id' => $user->id, 'wallet_balance' => 0]);
            } else {
                Seller::create(['user_id' => $user->id, 'wallet_balance' => 0]);
            }

            return $user;
        });

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'token'   => $token,
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
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }
}
