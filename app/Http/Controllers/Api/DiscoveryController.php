<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Models\Logement;
use App\Services\Ai\Insights\AgencyInsightsService;
use App\Services\Ai\Search\SmartSearchService;
use App\Services\Recommendation\RecommendationService;
use App\Support\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Discovery features: AI smart search, popular destinations, recommendations, agency insights.
 */
class DiscoveryController extends Controller
{
    /** POST /api/ai/smart-search {query} — public, throttled */
    public function smartSearch(Request $request, SmartSearchService $search): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:300'],
        ]);

        return response()->json(['success' => true, 'data' => $search->interpret($validated['query'])]);
    }

    /** GET /api/destinations — cities with active listings, count and starting price */
    public function destinations(): JsonResponse
    {
        $destinations = Logement::where('statut', 'actif')
            ->get(['adresse', 'tarif_amount', 'photo_url', 'devise'])
            ->groupBy(fn (Logement $l) => SmartSearchService::cityOf($l->adresse))
            ->filter(fn ($group, $city) => $city !== '')
            ->map(fn ($group, $city) => [
                'ville' => $city,
                'logements' => $group->count(),
                'prix_min' => (float) $group->min('tarif_amount'),
                'devise' => Currency::label($group->first()->devise),
                'photo_url' => $group->first()->photo_url,
            ])
            ->sortByDesc('logements')
            ->values();

        return response()->json(['success' => true, 'data' => $destinations]);
    }

    /** GET /api/recommendations — logged-in customer */
    public function recommendations(Request $request, RecommendationService $recommendations): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $recommendations->forUser($request->user())]);
    }

    /** GET /api/agency/assistant/insights — agency account */
    public function agencyInsights(Request $request, AgencyInsightsService $insights): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $insights->forAgency($request->user()->agence)]);
    }
}
