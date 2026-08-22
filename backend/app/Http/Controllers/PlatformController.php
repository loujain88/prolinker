<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * PlatformController
 *
 * Public, real platform statistics for the landing page — replaces the
 * previously hardcoded numbers (active sellers, completed orders, average
 * rating, "most searched" tags). We don't track search queries anywhere in
 * the schema, so "most searched" is honestly represented as "most ordered
 * categories" instead — a real signal, not a fabricated one.
 */
class PlatformController extends Controller
{
    public function stats(): JsonResponse
    {
        // Cheap 5-minute cache — this is a public, high-traffic endpoint and
        // the numbers don't need to be second-by-second accurate.
        $data = Cache::remember('platform.stats', 300, function () {
            $activeSellersCount   = User::where('role', 'seller')->where('is_active', true)->count();
            $verifiedSellersCount = Seller::where('is_verified', true)->count();

            $completedServicesCount = ServiceRequest::where('status', 'completed')->count();

            $avgRating = ServiceRequest::where('status', 'completed')
                ->whereNotNull('rating')
                ->avg('rating');

            $topCategories = ServiceCategory::withCount('services')
                ->get()
                ->map(function ($c) {
                    $c->orders_count = ServiceRequest::whereIn(
                        'service_id',
                        $c->services()->pluck('id')
                    )->count();
                    return $c;
                })
                ->sortByDesc('orders_count')
                ->take(5)
                ->pluck('name')
                ->values();

            return [
                'active_sellers_count'     => $activeSellersCount,
                'verified_sellers_count'   => $verifiedSellersCount,
                'completed_services_count' => $completedServicesCount,
                'average_rating'           => $avgRating ? round($avgRating, 1) : null,
                'top_categories'           => $topCategories,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
