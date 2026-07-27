<?php

namespace App\Http\Controllers;

use App\Http\Resources\MapStationResource;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StationSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $term = trim($validated['q'] ?? '');

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $normalizedTerm = Str::lower(Str::ascii($term));

        $stations = Station::query()
            ->where('is_active', true)
            ->with(['lines' => fn ($query) => $query->withCount('stations')->orderBy('sort_order')])
            ->orderBy('name')
            ->limit(80)
            ->get()
            ->filter(fn (Station $station) => str_contains(Str::lower(Str::ascii($station->name)), $normalizedTerm))
            ->take(8)
            ->values();

        return response()->json([
            'data' => MapStationResource::collection($stations)->resolve(),
        ]);
    }
}
