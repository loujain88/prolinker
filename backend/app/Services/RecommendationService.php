<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * RecommendationService
 *
 * Very deliberately simple, explainable scoring — no ML model, just weighted
 * signals, which is honest for a graduation-project scope and easy to defend:
 *
 *   +3  category the client picked as a preference at signup
 *   +2  per past order the client placed in that category (capped)
 *   +1  service is from a top-rated seller (>= 4.5 avg rating)
 *
 * Falls back to trending/highest-rated active services for a brand new
 * client with no preferences and no order history yet (cold start).
 */
class RecommendationService
{
    public function forClient(Client $client, int $limit = 12): Collection
    {
        $preferredIds = collect($client->preferred_category_ids ?? []);

        $orderedCategoryCounts = $client->requests()
            ->with('service:id,category_id')
            ->get()
            ->pluck('service.category_id')
            ->filter()
            ->countBy(); // [category_id => times ordered]

        $candidateCategoryIds = $preferredIds
            ->merge($orderedCategoryCounts->keys())
            ->unique()
            ->values();

        $services = Service::query()
            ->where('status', 'active')
            ->with(['seller.user', 'category'])
            ->withCount('requests')
            ->get();

        $scored = $services->map(function (Service $s) use ($preferredIds, $orderedCategoryCounts) {
            $score = 0;
            if ($preferredIds->contains($s->category_id)) {
                $score += 3;
            }
            $score += min((int) ($orderedCategoryCounts[$s->category_id] ?? 0), 3) * 2;
            if ((float) $s->seller?->average_rating >= 4.5) {
                $score += 1;
            }
            $s->setAttribute('_score', $score);
            return $s;
        });

        $isColdStart = $preferredIds->isEmpty() && $orderedCategoryCounts->isEmpty();

        $sorted = $isColdStart
            // Cold start: no signal yet — just show what's popular/well rated.
            ? $scored->sortByDesc(fn($s) => (float) ($s->seller?->average_rating ?? 0))
            : $scored
                ->sortByDesc(fn($s) => (float) ($s->seller?->average_rating ?? 0)) // tiebreaker
                ->sortByDesc(fn($s) => $s->_score);                               // stable primary sort

        return $sorted->take($limit)->values();
    }
}
