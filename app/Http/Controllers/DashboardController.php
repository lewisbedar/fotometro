<?php

namespace App\Http\Controllers;

use App\Enums\CoverageStatus;
use App\Models\Line;
use App\Models\Photo;
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
            'photoCount' => Photo::count(),
            'readyPhotoCount' => Photo::where('processing_status', 'ready')->count(),
            'pendingPhotoCount' => Photo::where('processing_status', 'pending')->count(),
            'processingPhotoCount' => Photo::where('processing_status', 'processing')->count(),
            'failedPhotoCount' => Photo::where('processing_status', 'failed')->count(),
            'publishedPhotoCount' => Photo::where('is_published', true)->count(),
            'stationsWithPhotosCount' => Station::whereHas('photos')->count(),
            'latestPhotos' => Photo::query()->with('station')->latest()->limit(5)->get(),
        ]);
    }
}
