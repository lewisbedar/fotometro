<?php

namespace App\Http\Controllers;

use App\Enums\CoverageStatus;
use App\Http\Resources\MapLineResource;
use App\Http\Resources\MapStationResource;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MapDataController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember('fotometro.public-map.v1', config('fotometro.map.cache_ttl'), function (): array {
            $lines = Line::query()
                ->with(['stations' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->with(['lines' => fn ($lineQuery) => $lineQuery->orderBy('sort_order')])])
                ->withCount('stations')
                ->orderBy('sort_order')
                ->get();

            $stations = Station::query()
                ->where('is_active', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->with(['lines' => fn ($query) => $query->withCount('stations')->orderBy('sort_order')])
                ->orderBy('name')
                ->get();

            $totalStations = Station::query()->where('is_active', true)->count();
            $documentedStations = Station::query()
                ->where('is_active', true)
                ->whereIn('coverage_status', [CoverageStatus::Documented->value, CoverageStatus::Complete->value])
                ->count();
            $stationsWithoutCoordinates = Station::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
                ->count();

            return [
                'lines' => MapLineResource::collection($lines)->resolve(),
                'stations' => MapStationResource::collection($stations)->resolve(),
                'coverage_statuses' => collect(CoverageStatus::cases())->map(fn (CoverageStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'description' => $status->description(),
                    'color' => $status->color(),
                ])->values()->all(),
                'progress' => [
                    'total_stations' => $totalStations,
                    'documented_stations' => $documentedStations,
                    'percentage' => $totalStations === 0 ? 0 : (int) round(($documentedStations / $totalStations) * 100),
                    'line_count' => $lines->count(),
                    'stations_without_coordinates' => $stationsWithoutCoordinates,
                ],
            ];
        });

        return response()->json($payload);
    }
}
