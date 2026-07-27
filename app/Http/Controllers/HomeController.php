<?php

namespace App\Http\Controllers;

use App\Enums\CoverageStatus;
use App\Models\Line;
use App\Models\Station;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $stationCount = Station::query()->where('is_active', true)->count();
        $documentedStationCount = Station::query()
            ->where('is_active', true)
            ->whereIn('coverage_status', [CoverageStatus::Documented->value, CoverageStatus::Complete->value])
            ->count();

        return view('home', [
            'lines' => Line::withCount('stations')->orderBy('sort_order')->get(),
            'stationCount' => $stationCount,
            'documentedStationCount' => $documentedStationCount,
            'progressPercentage' => $stationCount === 0 ? 0 : (int) round(($documentedStationCount / $stationCount) * 100),
            'lineCount' => Line::count(),
            'stationsWithoutCoordinates' => Station::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
                ->count(),
            'coverageStatuses' => CoverageStatus::cases(),
            'mapConfig' => config('fotometro.map'),
        ]);
    }
}
