<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * SupportController
 *
 * "Chat with admin" support system:
 *  - Starting a NEW request randomly assigns one of the active admins.
 *  - That assignment is STICKY: every reply in the same conversation keeps
 *    going to the same admin until it's closed. Only a fresh request (after
 *    the previous one is closed) gets reassigned randomly.
 */
class SupportController extends Controller
{
    /**
     * POST /api/v1/support/conversations
     * Starts a new support request, or returns the caller's existing open one.
     */
    public function store(): JsonResponse
    {
        $user = Auth::user();

        $conversation = SupportConversation::where('user_id', $user->id)
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $conversation) {
            $admin = \App\Models\User::where('role', 'admin')
                ->where('is_active', true)
                ->inRandomOrder()
                ->first();

            $conversation = SupportConversation::create([
                'user_id'  => $user->id,
                'admin_id' => $admin?->id,
                'status'   => 'open',
            ]);
        }

        return response()->json(['data' => $this->format($conversation->load(['admin']))]);
    }

    /**
     * GET /api/v1/support/conversations/{id}
     */
    public function show(int $id): JsonResponse
    {
        $conversation = $this->findAccessible($id);

        return response()->json([
            'data' => [
                'conversation' => $this->format($conversation->load('admin')),
                'messages'     => $conversation->messages()->with('sender:id,name,role')->get()->map(fn($m) => [
                    'id' => $m->id, 'sender_id' => $m->sender_id, 'sender_name' => $m->sender->name,
                    'sender_role' => $m->sender->role, 'body' => $m->body,
                    'created_at' => $m->created_at->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * POST /api/v1/support/conversations/{id}/messages
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $conversation = $this->findAccessible($id);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $message = $conversation->messages()->create([
            'sender_id' => Auth::id(),
            'body'      => $validated['body'],
        ]);
        $conversation->update(['last_message_at' => $message->created_at]);

        return response()->json(['data' => [
            'id' => $message->id, 'sender_id' => $message->sender_id,
            'sender_name' => Auth::user()->name, 'sender_role' => Auth::user()->role,
            'body' => $message->body, 'created_at' => $message->created_at->toIso8601String(),
        ]], 201);
    }

    /**
     * PATCH /api/v1/support/conversations/{id}/close
     * Either party can close it — the next request will get a fresh random admin.
     */
    public function close(int $id): JsonResponse
    {
        $conversation = $this->findAccessible($id);
        $conversation->update(['status' => 'closed']);

        return response()->json(['message' => 'تم إنهاء المحادثة.']);
    }

    /**
     * GET /api/v1/admin/support-conversations
     * The current admin's assigned support threads.
     */
    public function adminIndex(): JsonResponse
    {
        $conversations = SupportConversation::where('admin_id', Auth::id())
            ->with('user:id,name,email,role')
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json(['data' => $conversations->map(fn($c) => $this->format($c))]);
    }

    private function findAccessible(int $id): SupportConversation
    {
        $conversation = SupportConversation::findOrFail($id);
        $user = Auth::user();

        abort_unless(
            $conversation->user_id === $user->id || $conversation->admin_id === $user->id,
            403,
            'You do not have access to this conversation.'
        );

        return $conversation;
    }

    private function format(SupportConversation $c): array
    {
        return [
            'id'         => $c->id,
            'status'     => $c->status,
            'admin_name' => $c->relationLoaded('admin') ? ($c->admin->name ?? 'قيد التعيين') : null,
            'user_name'  => $c->relationLoaded('user') ? $c->user->name : null,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'created_at' => $c->created_at->toIso8601String(),
        ];
    }
}
