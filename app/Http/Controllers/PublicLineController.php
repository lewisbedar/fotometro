<?php

namespace App\Http\Controllers;

use App\Enums\CoverageStatus;
use App\Models\Line;
use Illuminate\View\View;

class PublicLineController extends Controller
{
    public function show(Line $line): View
    {
        $line->load(['stations' => fn ($query) => $query->where('is_active', true)->with('lines')]);

        $stationCount = $line->stations->count();
        $documentedCount = $line->stations
            ->whereIn('coverage_status', [CoverageStatus::Documented, CoverageStatus::Complete])
            ->count();

        return view('lines.show', [
            'line' => $line,
            'stationCount' => $stationCount,
            'documentedCount' => $documentedCount,
            'coveragePercentage' => $stationCount === 0 ? 0 : (int) round(($documentedCount / $stationCount) * 100),
            'mapConfig' => config('fotometro.map'),
            'lineStationCoordinates' => $line->stations
                ->filter(fn ($station) => $station->latitude !== null && $station->longitude !== null)
                ->map(fn ($station) => [
                    'longitude' => (float) $station->longitude,
                    'latitude' => (float) $station->latitude,
                    'name' => $station->name,
                    'status_color' => $station->coverage_status->color(),
                ])
                ->values(),
            'metaDescription' => "Stations et progression photographique de {$line->name} sur fotometro.",
        ]);
    }
}
