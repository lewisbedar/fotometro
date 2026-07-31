<?php

namespace App\Http\Controllers;

use App\Http\Resources\MapStationResource;
use App\Models\Line;
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
            ->with([
                'lines' => fn ($query) => $query->where('is_active', true)->withCount('stations')->orderBy('sort_order'),
                'coverPhoto',
            ])
            ->withCount('accesses')
            ->orderBy('name')
            ->get()
            ->filter(fn (Station $station) => str_contains(Str::lower(Str::ascii($station->name)), $normalizedTerm))
            ->take(20)
            ->values();

        $lines = Line::query()
            ->where('is_active', true)
            ->withCount('stations')
            ->orderBy('sort_order')
            ->limit(80)
            ->get()
            ->filter(fn (Line $line) => str_contains(Str::lower(Str::ascii($line->name.' '.$line->code)), $normalizedTerm))
            ->take(5)
            ->map(fn (Line $line) => [
                'id' => $line->id,
                'code' => $line->code,
                'name' => $line->name,
                'slug' => $line->slug,
                'color' => $line->color,
                'text_color' => $line->text_color,
                'station_count' => $line->stations_count,
                'url' => route('lines.show', $line->slug),
            ])
            ->values();

        return response()->json([
            'data' => MapStationResource::collection($stations)->resolve(),
            'lines' => $lines,
        ]);
    }
}
