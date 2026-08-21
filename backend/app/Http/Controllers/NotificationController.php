<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * NotificationController
 *
 * Delivers in-app notifications to the authenticated user.
 *
 * Endpoints:
 *   GET    /api/v1/notifications           Paginated list (newest first)
 *   GET    /api/v1/notifications/unread-count  Bell-icon badge count
 *   PATCH  /api/v1/notifications/{id}/read  Mark one as read
 *   PATCH  /api/v1/notifications/read-all   Mark all as read
 *   DELETE /api/v1/notifications/{id}       Delete one notification
 *
 * Polling strategy (for graduation project):
 *   The Vue.js frontend can poll GET /notifications/unread-count every
 *   30 seconds. When count > 0, fetch the full notification list.
 *
 *   When you are ready to add real-time push:
 *   1. Install Laravel Echo + Pusher (or Laravel Reverb for self-hosted).
 *   2. Fire a NotificationSent event from NotificationService::send().
 *   3. Broadcast it on a private channel: "notifications.{userId}".
 *   The REST endpoints below remain valid as the fallback/initial-load path.
 */
class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications
     *
     * Returns paginated notifications for the authenticated user.
     *
     * Query params:
     *   - unread_only  bool   Return only unread notifications (default false)
     *   - per_page     int    Items per page (default 20, max 50)
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread_only' => ['nullable', 'boolean'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Notification::forUser(Auth::id())
            ->latest('created_at');

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $notifications = $query->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => $notifications->map(fn($n) => $this->formatNotification($n)),
            'meta' => [
                'current_page'  => $notifications->currentPage(),
                'last_page'     => $notifications->lastPage(),
                'total'         => $notifications->total(),
                'unread_count'  => $this->getUnreadCount(),
            ],
        ]);
    }

    /**
     * GET /api/v1/notifications/unread-count
     *
     * Lightweight endpoint for the bell-icon badge.
     * Designed to be polled frequently without heavy DB load.
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->getUnreadCount(),
        ]);
    }

    /**
     * PATCH /api/v1/notifications/{id}/read
     *
     * Mark a single notification as read.
     * Idempotent — safe to call multiple times.
     */
    public function markAsRead(int $id): JsonResponse
    {
        $notification = Notification::forUser(Auth::id())
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
            'data'    => $this->formatNotification($notification->fresh()),
        ]);
    }

    /**
     * PATCH /api/v1/notifications/read-all
     *
     * Bulk-mark all of the user's unread notifications as read.
     * Returns the count of notifications updated.
     */
    public function markAllAsRead(): JsonResponse
    {
        $count = Notification::forUser(Auth::id())
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'message'       => "Marked {$count} notification(s) as read.",
            'updated_count' => $count,
        ]);
    }

    /**
     * DELETE /api/v1/notifications/{id}
     *
     * Permanently delete a single notification.
     * Only the owner can delete their own notifications.
     */
    public function destroy(int $id): JsonResponse
    {
        $notification = Notification::forUser(Auth::id())->findOrFail($id);
        $notification->delete();

        return response()->json(['message' => 'Notification deleted.']);
    }

    /**
     * DELETE /api/v1/notifications/clear-all
     *
     * Delete all notifications for the authenticated user.
     * Useful for "clear all" UX pattern.
     */
    public function clearAll(): JsonResponse
    {
        $count = Notification::forUser(Auth::id())->delete();

        return response()->json([
            'message'        => "Cleared {$count} notification(s).",
            'deleted_count'  => $count,
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function getUnreadCount(): int
    {
        return Notification::forUser(Auth::id())->unread()->count();
    }

    private function formatNotification(Notification $n): array
    {
        return [
            'id'         => $n->id,
            'type'       => $n->type,
            'content'    => $n->content,
            'action_url' => $n->action_url,
            'is_read'    => $n->isRead(),
            'read_at'    => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
            'entity'     => $n->notifiable_type ? [
                'type' => class_basename($n->notifiable_type),
                'id'   => $n->notifiable_id,
            ] : null,
        ];
    }
}
