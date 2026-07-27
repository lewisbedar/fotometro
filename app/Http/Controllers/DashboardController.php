<?php

namespace App\Http\Controllers;

use App\Enums\CoverageStatus;
use App\Models\Line;
use App\Models\Station;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'lineCount' => Line::count(),
            'stationCount' => Station::count(),
            'documentedStationCount' => Station::whereIn('coverage_status', [
                CoverageStatus::Documented,
                CoverageStatus::Complete,
            ])->count(),
            'undocumentedStationCount' => Station::where('coverage_status', CoverageStatus::NotStarted)->count(),
        ]);
    }
}
