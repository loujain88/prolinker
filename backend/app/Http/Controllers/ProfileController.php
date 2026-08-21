<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * ProfileController
 *
 * PUT /api/v1/client/profile
 * PUT /api/v1/seller/profile
 */
class ProfileController extends Controller
{
    public function updateClient(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->isClient(), 403, 'Only clients can update a client profile.');

        $validated = $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'email'        => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'        => ['nullable', 'string', 'max:30'],
            'bio'          => ['nullable', 'string', 'max:2000'],
            'country'      => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['name']) || isset($validated['email'])) {
            $user->update(array_filter([
                'name'  => $validated['name']  ?? null,
                'email' => $validated['email'] ?? null,
            ], fn($v) => $v !== null));
        }

        $user->client->update(array_intersect_key($validated, array_flip([
            'phone', 'bio', 'country', 'company_name',
        ])));

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => $this->currentUserPayload($user),
        ]);
    }

    public function updateSeller(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->isSeller(), 403, 'Only sellers can update a seller profile.');

        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'email'         => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'         => ['nullable', 'string', 'max:30'],
            'bio'           => ['nullable', 'string', 'max:2000'],
            'country'       => ['nullable', 'string', 'max:100'],
            'tagline'       => ['nullable', 'string', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
        ]);

        if (isset($validated['name']) || isset($validated['email'])) {
            $user->update(array_filter([
                'name'  => $validated['name']  ?? null,
                'email' => $validated['email'] ?? null,
            ], fn($v) => $v !== null));
        }

        $user->seller->update(array_intersect_key($validated, array_flip([
            'phone', 'bio', 'country', 'tagline', 'portfolio_url',
        ])));

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => $this->currentUserPayload($user),
        ]);
    }

    private function currentUserPayload($user): array
    {
        $user->refresh()->load(['client', 'seller']);

        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
            'client' => $user->client ? $user->client->only(['phone', 'bio', 'country', 'company_name']) : null,
            'seller' => $user->seller ? $user->seller->only(['phone', 'bio', 'country', 'tagline', 'portfolio_url']) : null,
        ];
    }
}
