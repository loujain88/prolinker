<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Service;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * MessageController
 *
 * Client ⇄ Seller conversation threads, scoped to a service.
 * A client can message a seller BEFORE placing an order (to negotiate
 * scope / send project details), and the thread continues after an
 * order is placed. Admins can read every conversation for moderation.
 *
 * Endpoints:
 *   GET    /api/v1/conversations                 My conversations (client or seller)
 *   POST   /api/v1/client/conversations           Start (or reuse) a thread + first message
 *   GET    /api/v1/conversations/{id}             Thread messages (marks as read)
 *   POST   /api/v1/conversations/{id}/messages    Reply in an existing thread
 *   GET    /api/v1/admin/conversations            All threads (admin)
 *   GET    /api/v1/admin/conversations/{id}        Full thread (admin)
 */
class MessageController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {}

    /**
     * GET /api/v1/conversations
     *
     * List the authenticated user's conversations (as client or seller),
     * newest activity first, with an unread indicator.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $query = Conversation::with(['service:id,title,thumbnail_path', 'client.user:id,name', 'seller.user:id,name'])
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->whereNull('read_at')->where('sender_id', '!=', $user->id);
            }])
            ->orderByDesc('last_message_at');

        if ($user->isClient()) {
            $query->whereHas('client', fn($q) => $q->where('user_id', $user->id));
        } elseif ($user->isSeller()) {
            $query->whereHas('seller', fn($q) => $q->where('user_id', $user->id));
        } else {
            abort(403, 'Only clients and sellers have conversations.');
        }

        $conversations = $query->get()->map(fn($c) => $this->formatConversation($c));

        return response()->json(['data' => $conversations]);
    }

    /**
     * POST /api/v1/client/conversations
     *
     * Client starts (or continues) a thread with the seller of a given
     * service, sending an initial message — typically the project details
     * they want quoted BEFORE committing to a purchase.
     *
     * Body: service_id (required), body (required, the message text)
     */
    public function store(Request $request): JsonResponse
    {
        $this->gateClient();

        $validated = $request->validate([
            'service_id'      => ['required', 'integer', 'exists:services,id'],
            'body'            => ['required', 'string', 'max:5000'],
            'attachments'     => ['nullable', 'array', 'max:5'],
            'attachments.*'   => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,zip,rar,fig,psd,ai,txt'],
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $client  = Auth::user()->client;
        $attachments = $this->storeAttachments($request, 'conversations/' . $service->id);

        $conversation = DB::transaction(function () use ($service, $client, $validated, $attachments) {
            // Reuse the conversation only if it's still "open" (not yet tied to
            // a placed order). Once an order is created from a conversation,
            // any further contact for the same service starts a brand-new
            // thread — this keeps each order's files/messages separate.
            $conversation = Conversation::where('client_id', $client->id)
                ->where('seller_id', $service->seller_id)
                ->where('service_id', $service->id)
                ->whereNull('request_id')
                ->latest()
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'client_id'  => $client->id,
                    'seller_id'  => $service->seller_id,
                    'service_id' => $service->id,
                ]);
            }

            $message = $conversation->messages()->create([
                'sender_id'   => Auth::id(),
                'body'        => $validated['body'],
                'attachments' => $attachments ?: null,
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            $this->notifications->newMessage($service->seller->user, $message);

            return $conversation;
        });

        return response()->json([
            'message' => 'Message sent to the seller.',
            'data'    => $this->formatConversation($conversation->fresh(['service', 'client.user', 'seller.user'])),
        ], 201);
    }

    /**
     * GET /api/v1/conversations/{id}
     *
     * Fetch full message history for a thread. Marks the other party's
     * messages as read for the current viewer.
     */
    public function show(int $id): JsonResponse
    {
        $conversation = $this->findAccessibleConversation($id);

        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', Auth::id())
            ->update(['read_at' => now()]);

        $messages = $conversation->messages()->with('sender:id,name,role')->get()
            ->map(fn($m) => $this->formatMessage($m));

        return response()->json([
            'data' => [
                'conversation' => $this->formatConversation($conversation),
                'messages'     => $messages,
            ],
        ]);
    }

    /**
     * POST /api/v1/conversations/{id}/messages
     *
     * Send a follow-up message in an existing thread (client or seller side).
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $conversation = $this->findAccessibleConversation($id);

        $validated = $request->validate([
            'body'           => ['required', 'string', 'max:5000'],
            'attachments'    => ['nullable', 'array', 'max:5'],
            'attachments.*'  => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,zip,rar,fig,psd,ai,txt'],
        ]);

        $attachments = $this->storeAttachments($request, 'conversations/' . $conversation->service_id);

        $message = DB::transaction(function () use ($conversation, $validated, $attachments) {
            $message = $conversation->messages()->create([
                'sender_id'   => Auth::id(),
                'body'        => $validated['body'],
                'attachments' => $attachments ?: null,
            ]);
            $conversation->update(['last_message_at' => $message->created_at]);
            return $message;
        });

        $recipientUser = Auth::id() === $conversation->client->user_id
            ? $conversation->seller->user
            : $conversation->client->user;
        $this->notifications->newMessage($recipientUser, $message);

        return response()->json([
            'message' => 'Message sent.',
            'data'    => $this->formatMessage($message->load('sender:id,name,role')),
        ], 201);
    }

    // =========================================================================
    // ADMIN — OVERSIGHT
    // =========================================================================

    /**
     * GET /api/v1/admin/conversations
     */
    public function adminIndex(): JsonResponse
    {
        $conversations = Conversation::with(['service:id,title', 'client.user:id,name', 'seller.user:id,name'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return response()->json([
            'data' => collect($conversations->items())->map(fn($c) => $this->formatConversation($c)),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page'    => $conversations->lastPage(),
                'total'        => $conversations->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/conversations/{id}
     */
    public function adminShow(int $id): JsonResponse
    {
        $conversation = Conversation::with(['service', 'client.user', 'seller.user'])->findOrFail($id);

        $messages = $conversation->messages()->with('sender:id,name,role')->get()
            ->map(fn($m) => $this->formatMessage($m));

        return response()->json([
            'data' => [
                'conversation' => $this->formatConversation($conversation),
                'messages'     => $messages,
            ],
        ]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function findAccessibleConversation(int $id): Conversation
    {
        $conversation = Conversation::with(['client', 'seller'])->findOrFail($id);
        $user = Auth::user();

        if (! $user->isAdmin() && ! $conversation->hasParticipant($user)) {
            abort(403, 'You do not have access to this conversation.');
        }

        return $conversation;
    }

    private function gateClient(): void
    {
        $user = Auth::user();
        if (! $user?->isActive()) abort(403, 'Your account is suspended.');
        if (! $user->isClient()) abort(403, "Only clients can start a conversation with a seller.");
    }

    private function storeAttachments(Request $request, string $folder): array
    {
        if (! $request->hasFile('attachments')) return [];

        $stored = [];
        foreach ($request->file('attachments') as $file) {
            $path = $file->store("messages/{$folder}", 'public');
            $stored[] = [
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getClientMimeType(),
                'url'  => \Illuminate\Support\Facades\Storage::disk('public')->url($path),
            ];
        }
        return $stored;
    }

    private function formatConversation(Conversation $c): array
    {
        return [
            'id'               => $c->id,
            'service'          => $c->relationLoaded('service') ? [
                'id'    => $c->service->id,
                'title' => $c->service->title,
            ] : null,
            'client_name'      => $c->relationLoaded('client') ? $c->client->user->name : null,
            'seller_name'      => $c->relationLoaded('seller') ? $c->seller->user->name : null,
            'request_id'       => $c->request_id,
            'unread_count'     => $c->unread_count ?? 0,
            'messages_count'   => $c->messages_count ?? null,
            'last_message_at'  => $c->last_message_at?->toIso8601String(),
            'created_at'       => $c->created_at->toIso8601String(),
        ];
    }

    private function formatMessage(Message $m): array
    {
        return [
            'id'             => $m->id,
            'conversation_id'=> $m->conversation_id,
            'sender_id'      => $m->sender_id,
            'sender_name'    => $m->relationLoaded('sender') ? $m->sender->name : null,
            'sender_role'    => $m->relationLoaded('sender') ? $m->sender->role : null,
            'body'           => $m->body,
            'order_details'  => $m->order_details,
            'attachments'    => $m->attachments ?? [],
            'read_at'        => $m->read_at?->toIso8601String(),
            'created_at'     => $m->created_at->toIso8601String(),
        ];
    }
}
