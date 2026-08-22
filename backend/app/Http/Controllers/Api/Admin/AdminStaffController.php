<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * AdminStaffController
 *
 * Lets the super admin add/remove staff accounts that have full admin
 * permissions EXCEPT this controller itself — staff cannot add, edit, or
 * remove other staff members. Only the super admin can.
 *
 * GET    /api/admin/staff        — list staff admins
 * POST   /api/admin/staff        — create a new staff admin
 * DELETE /api/admin/staff/{id}   — remove a staff admin
 */
class AdminStaffController extends Controller
{
    private function ensureSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403, 'فقط المدير الرئيسي يقدر يدير الموظفين.');
    }

    public function index(): JsonResponse
    {
        $this->ensureSuperAdmin();

        $staff = User::where('role', 'admin')
            ->where('is_super_admin', false)
            ->latest()
            ->get();

        return response()->json(['data' => $staff->map(fn($u) => $this->format($u))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $staff = User::create([
            'name'            => $validated['name'],
            'email'           => $validated['email'],
            'password'        => Hash::make($validated['password']),
            'role'            => 'admin',
            'is_super_admin'  => false, // staff — never the super admin
            'is_active'       => true,
            'approval_status' => 'approved', // admins skip the signup approval flow
            'approved_at'     => now(),
        ]);

        return response()->json(['message' => 'تمت إضافة الموظف بنجاح.', 'data' => $this->format($staff)], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->ensureSuperAdmin();

        $staff = User::where('role', 'admin')->where('is_super_admin', false)->findOrFail($id);
        $staff->tokens()->delete(); // revoke active sessions
        $staff->delete();

        return response()->json(['message' => 'تم حذف الموظف.']);
    }

    private function format(User $u): array
    {
        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
            'is_active' => $u->is_active, 'created_at' => $u->created_at->toIso8601String(),
        ];
    }
}
