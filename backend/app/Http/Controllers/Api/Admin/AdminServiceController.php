<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/admin/services          — All services (filterable)
 * GET /api/admin/services/pending  — Paused services awaiting review
 * PUT /api/admin/services/{id}/status — active | paused | suspended
 */
class AdminServiceController extends Controller
{
    public function pending(): JsonResponse
    {
        $services = Service::with(['seller.user', 'category'])
            ->where('status', 'paused')
            ->latest()
            ->get()
            ->map(fn($s) => $this->format($s));

        return response()->json(['data' => $services]);
    }

    public function index(Request $request): JsonResponse
    {
        $services = Service::with(['seller.user', 'category'])
            ->when($request->status,      fn($q, $s) => $q->where('status', $s))
            ->when($request->category_id, fn($q, $v) => $q->where('category_id', $v))
            ->when($request->search,      fn($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($services->items())->map(fn($s) => $this->format($s)),
            'meta' => ['total' => $services->total(), 'current_page' => $services->currentPage(), 'last_page' => $services->lastPage()],
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,paused,suspended'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $service   = Service::with('seller.user')->findOrFail($id);
        $oldStatus = $service->status;
        $service->update(['status' => $validated['status']]);

        $statusAr = match ($validated['status']) {
            'active'    => 'تم قبول الخدمة ونشرها ✅',
            'paused'    => 'تم إيقاف الخدمة مؤقتاً ⏸',
            'suspended' => 'تم تعليق الخدمة من قِبل الإدارة 🚫',
        };

        $content = "تغيرت حالة خدمتك «{$service->title}»: {$statusAr}.";
        if ($validated['notes']) $content .= " ملاحظة: {$validated['notes']}";

        Notification::create([
            'user_id'         => $service->seller->user_id,
            'type'            => 'service.status_changed',
            'content'         => $content,
            'action_url'      => '/seller/services',
            'notifiable_type' => Service::class,
            'notifiable_id'   => $service->id,
        ]);

        return response()->json([
            'message' => "تم تغيير حالة الخدمة #{$id} من '{$oldStatus}' إلى '{$validated['status']}'.",
            'data'    => $this->format($service->fresh('seller.user', 'category')),
        ]);
    }

    private function format(Service $s): array
    {
        return [
            'id' => $s->id, 'title' => $s->title, 'description' => $s->description,
            'status' => $s->status, 'dynamic_price' => $s->dynamic_price, 'rate' => $s->rate,
            'delivery_days' => $s->delivery_days, 'average_rating' => $s->average_rating,
            'total_orders' => $s->total_orders,
            'category' => $s->category?->name,
            'thumbnail_url' => $s->thumbnail_path ? \Storage::disk('public')->url($s->thumbnail_path) : null,
            'seller' => $s->relationLoaded('seller') ? [
                'id' => $s->seller->id, 'name' => $s->seller->user->name, 'email' => $s->seller->user->email,
            ] : null,
            'created_at' => $s->created_at->toIso8601String(),
        ];
    }
}
