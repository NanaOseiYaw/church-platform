<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin controller — delegates all logic to SearchService.
 *
 * GET /dashboard/search?q={query}&limit={perGroup}
 *
 * Returns:
 * {
 *   "groups": [
 *     { "type": "member", "label": "Members", "results": [...] },
 *     { "type": "event",  "label": "Events",  "results": [...] },
 *     ...
 *   ],
 *   "query": "john",
 *   "total": 12
 * }
 */
class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query    = trim($request->string('q')->toString());
        $limit    = (int) $request->input('limit', 5);
        $user     = $request->user();
        $churchId = $this->resolvedChurchId();

        // Return early so the frontend knows the query was too short
        if (mb_strlen($query) < 2) {
            return response()->json(['groups' => [], 'query' => $query, 'total' => 0]);
        }

        // Clamp limit: minimum 3, maximum 10 per group
        $limit = max(3, min(10, $limit));

        $groups = $this->search->search($query, $user, $churchId, $limit);

        $total = array_sum(array_map(fn ($g) => count($g['results']), $groups));

        return response()->json([
            'groups' => $groups,
            'query'  => $query,
            'total'  => $total,
        ]);
    }
}
