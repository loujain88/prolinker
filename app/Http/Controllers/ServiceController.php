<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * ServiceController
 *
 * Full CRUD for service listings plus public browsing with filtering.
 *
 * Public endpoints (no auth):
 *   GET /api/v1/services         Browse + search
 *   GET /api/v1/services/{id}    Single service detail
 *   GET /api/v1/categories       All categories
 *
 * Seller endpoints:
 *   POST   /api/v1/services
 *   PUT    /api/v1/services/{id}
 *   DELETE /api/v1/services/{id}
 *   GET    /api/v1/seller/services   My listings
 */
class ServiceController extends Controller
{
    // =========================================================================
    // PUBLIC — BROWSE
    // =========================================================================

    /**
     * GET /api/v1/services
     *
     * Browse all active services with filtering, search, and sorting.
     *
     * Query params:
     *   - q           string   Full-text search term
     *   - category_id int      Filter by category
     *   - min_price   float    Minimum dynamic_price
     *   - max_price   float    Maximum dynamic_price
     *   - sort        string   newest|price_asc|price_desc|rating|popular
     *   - per_page    int      Items per page (default 12, max 50)
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q'           => ['nullable', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'min_price'   => ['nullable', 'numeric', 'min:0'],
            'max_price'   => ['nullable', 'numeric', 'min:0'],
            'sort'        => ['nullable', 'string', 'in:newest,price_asc,price_desc,rating,popular'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $services = Service::active()
            ->with(['seller.user', 'category'])
            ->byCategory($validated['category_id'] ?? null)
            ->byPriceRange($validated['min_price'] ?? null, $validated['max_price'] ?? null)
            ->search($validated['q'] ?? null)
            ->sortBy($validated['sort'] ?? 'newest')
            ->paginate($validated['per_page'] ?? 12);

        return response()->json([
            'data'  => $services->items(),
            'meta'  => [
                'current_page' => $services->currentPage(),
                'last_page'    => $services->lastPage(),
                'total'        => $services->total(),
                'per_page'     => $services->perPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/services/{id}
     *
     * Retrieve a single service with full seller details.
     */
    public function show(int $id): JsonResponse
    {
        $service = Service::with(['seller.user', 'category'])
            ->where('status', 'active')
            ->findOrFail($id);

        return response()->json(['data' => $this->formatService($service, detailed: true)]);
    }

    /**
     * GET /api/v1/categories
     *
     * Return all service categories for use in filter dropdowns.
     */
    public function categories(): JsonResponse
    {
        $categories = ServiceCategory::withCount('services')->get();
        return response()->json(['data' => $categories]);
    }

    // =========================================================================
    // SELLER — CRUD
    // =========================================================================

    /**
     * POST /api/v1/services
     *
     * Seller creates a new service listing.
     *
     * Body (multipart/form-data):
     *   - title               string   required
     *   - description         string   required
     *   - category_id         int      optional
     *   - rate                float    required  (hourly/unit rate)
     *   - dynamic_price       float    required  (listed price)
     *   - delivery_days       int      required
     *   - revisions_included  int      required
     *   - thumbnail           file     optional  (jpeg|png, max 2 MB)
     *   - gallery[]           file[]   optional  (up to 5 images)
     */
    public function store(Request $request): JsonResponse
    {
        $this->gate('seller');

        $validated = $request->validate([
            'title'               => ['required', 'string', 'min:10', 'max:255'],
            'description'         => ['required', 'string', 'min:50', 'max:5000'],
            'category_id'         => ['nullable', 'integer', 'exists:service_categories,id'],
            'rate'                => ['required', 'numeric', 'min:1', 'max:10000'],
            'dynamic_price'       => ['required', 'numeric', 'min:1', 'max:10000'],
            'delivery_days'       => ['required', 'integer', 'min:1', 'max:90'],
            'revisions_included'  => ['required', 'integer', 'min:0', 'max:20'],
            'thumbnail'           => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'gallery'             => ['nullable', 'array', 'max:5'],
            'gallery.*'           => ['file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $seller = Auth::user()->seller;

        // Store thumbnail
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')
                ->store("services/{$seller->id}/thumbnails", 'public');
        }

        // Store gallery images
        $galleryPaths = [];
        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $image) {
                $galleryPaths[] = $image->store("services/{$seller->id}/gallery", 'public');
            }
        }

        $service = Service::create([
            'seller_id'          => $seller->id,
            'category_id'        => $validated['category_id'] ?? null,
            'title'              => $validated['title'],
            'description'        => $validated['description'],
            'rate'               => $validated['rate'],
            'dynamic_price'      => $validated['dynamic_price'],
            'delivery_days'      => $validated['delivery_days'],
            'revisions_included' => $validated['revisions_included'],
            'thumbnail_path'     => $thumbnailPath,
            'gallery_paths'      => $galleryPaths ?: null,
            'status'             => 'active',
        ]);

        return response()->json([
            'message' => 'Service created successfully.',
            'data'    => $this->formatService($service->load('seller.user', 'category')),
        ], 201);
    }

    /**
     * PUT /api/v1/services/{id}
     *
     * Seller updates their service. Only the owning seller can update.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->gate('seller');

        $service = $this->findOwnedService($id);

        $validated = $request->validate([
            'title'               => ['sometimes', 'string', 'min:10', 'max:255'],
            'description'         => ['sometimes', 'string', 'min:50', 'max:5000'],
            'category_id'         => ['nullable', 'integer', 'exists:service_categories,id'],
            'rate'                => ['sometimes', 'numeric', 'min:1', 'max:10000'],
            'dynamic_price'       => ['sometimes', 'numeric', 'min:1', 'max:10000'],
            'delivery_days'       => ['sometimes', 'integer', 'min:1', 'max:90'],
            'revisions_included'  => ['sometimes', 'integer', 'min:0', 'max:20'],
            'status'              => ['sometimes', 'in:active,paused'],
            'thumbnail'           => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        // Replace thumbnail if new one uploaded
        if ($request->hasFile('thumbnail')) {
            if ($service->thumbnail_path) {
                Storage::disk('public')->delete($service->thumbnail_path);
            }
            $validated['thumbnail_path'] = $request->file('thumbnail')
                ->store("services/{$service->seller_id}/thumbnails", 'public');
        }

        unset($validated['thumbnail']);
        $service->update($validated);

        return response()->json([
            'message' => 'Service updated successfully.',
            'data'    => $this->formatService($service->fresh(['seller.user', 'category'])),
        ]);
    }

    /**
     * DELETE /api/v1/services/{id}
     *
     * Soft-deletes the service. Active orders are not affected.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->gate('seller');

        $service = $this->findOwnedService($id);

        // Prevent deletion if there are active orders
        $activeOrders = $service->requests()
            ->whereIn('status', ['pending', 'in_progress', 'delivered'])
            ->count();

        if ($activeOrders > 0) {
            return response()->json([
                'message' => "Cannot delete this service — it has {$activeOrders} active order(s). Pause it instead.",
            ], 422);
        }

        $service->delete();

        return response()->json(['message' => 'Service deleted successfully.']);
    }

    /**
     * GET /api/v1/seller/services
     *
     * Seller's own listings, including paused/suspended ones.
     */
    public function myServices(Request $request): JsonResponse
    {
        $this->gate('seller');

        $seller = Auth::user()->seller;

        $services = Service::where('seller_id', $seller->id)
            ->with('category')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->withCount([
                'requests',
                'requests as active_orders_count' => fn($q) =>
                    $q->whereIn('status', ['pending', 'in_progress', 'delivered']),
            ])
            ->latest()
            ->paginate(15);

        return response()->json(['data' => $services]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function findOwnedService(int $id): Service
    {
        $seller = Auth::user()->seller;
        return Service::where('id', $id)
            ->where('seller_id', $seller->id)
            ->firstOrFail();
    }

    private function formatService(Service $s, bool $detailed = false): array
    {
        $data = [
            'id'                 => $s->id,
            'title'              => $s->title,
            'dynamic_price'      => $s->dynamic_price,
            'rate'               => $s->rate,
            'delivery_days'      => $s->delivery_days,
            'revisions_included' => $s->revisions_included,
            'average_rating'     => $s->average_rating,
            'total_orders'       => $s->total_orders,
            'status'             => $s->status,
            'thumbnail_url'      => $s->thumbnail_path
                ? Storage::disk('public')->url($s->thumbnail_path)
                : null,
            'category'           => $s->relationLoaded('category') ? $s->category?->name : null,
            'seller'             => $s->relationLoaded('seller') ? [
                'id'             => $s->seller->id,
                'name'           => $s->seller->user->name,
                'average_rating' => $s->seller->average_rating,
                'is_verified'    => $s->seller->is_verified,
            ] : null,
            'created_at'         => $s->created_at->toIso8601String(),
        ];

        if ($detailed) {
            $data['description']  = $s->description;
            $data['gallery_urls'] = collect($s->gallery_paths ?? [])
                ->map(fn($p) => Storage::disk('public')->url($p))
                ->toArray();
        }

        return $data;
    }

    private function gate(string $role): void
    {
        $user = Auth::user();
        if (! $user?->isActive()) abort(403, 'Your account is suspended.');
        if ($user->role !== $role) abort(403, "Requires '{$role}' role.");
    }
}
