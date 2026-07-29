<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\PhotoCategoryController;
use App\Http\Controllers\Admin\PhotoController;
use App\Http\Controllers\Admin\PhotoSelectionApiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapDataController;
use App\Http\Controllers\PublicPhotoController;
use App\Http\Controllers\PublicLineController;
use App\Http\Controllers\PublicStationController;
use App\Http\Controllers\StationSearchController;
use App\Http\Resources\MapLineResource;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', HomeController::class)->name('home');
Route::get('/map-diagnostic', fn () => app()->isLocal()
    ? view('map-diagnostic')
    : abort(404))->name('map.diagnostic');
Route::get('/map-line-diagnostic', fn () => app()->isLocal()
    ? view('map-line-diagnostic')
    : abort(404))->name('map.line-diagnostic');
Route::get('/debug/database', fn () => app()->isLocal()
    ? response()->json([
        'driver' => DB::connection()->getDriverName(),
        'database' => DB::connection()->getDatabaseName(),
        'lines' => Line::query()->count(),
        'stations' => Station::query()->count(),
        'station_line_relations' => DB::table('station_line')->count(),
    ])
    : abort(404))->name('debug.database');
Route::get('/debug/diagram-container', fn () => app()->isLocal()
    ? view('debug.diagram-container')
    : abort(404))->name('debug.diagram-container');
Route::get('/debug/line-diagrams', function () {
    if (! app()->isLocal()) {
        abort(404);
    }

    $lines = Line::query()
        ->where('is_active', true)
        ->with(['stations' => fn ($query) => $query
            ->where('is_active', true)
            ->with(['lines' => fn ($lineQuery) => $lineQuery->where('is_active', true)->orderBy('sort_order')])
            ->withCount('accesses')])
        ->with(['stationSequences' => fn ($query) => $query
            ->with(['station' => fn ($stationQuery) => $stationQuery
                ->where('is_active', true)
                ->with(['lines' => fn ($lineQuery) => $lineQuery->where('is_active', true)->orderBy('sort_order')])
                ->withCount('accesses')])])
        ->withCount('stations')
        ->orderBy('sort_order')
        ->get();

    return view('debug.line-diagrams', [
        'lines' => MapLineResource::collection($lines)->resolve(),
    ]);
})->name('debug.line-diagrams');
Route::get('/api/map', MapDataController::class)->name('api.map');
Route::get('/api/map/search', StationSearchController::class)->middleware('throttle:map-search')->name('api.map.search');
Route::get('/stations/{station:slug}', [PublicStationController::class, 'show'])->name('stations.show');
Route::get('/lignes/{line:slug}', [PublicLineController::class, 'show'])->name('lines.show');
Route::get('/photos/{photo:slug}', [PublicPhotoController::class, 'show'])->name('photos.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/admin', DashboardController::class)->name('admin.dashboard');
    Route::get('/admin/api/lines/{line}/stations', [PhotoSelectionApiController::class, 'stations'])->name('admin.api.lines.stations');
    Route::get('/admin/api/stations/{station}/accesses', [PhotoSelectionApiController::class, 'accesses'])->name('admin.api.stations.accesses');
    Route::post('/admin/photos/detect-station', [PhotoSelectionApiController::class, 'detectStation'])->name('admin.api.photos.detect-station');
    Route::resource('/admin/photo-categories', PhotoCategoryController::class)
        ->except(['show', 'destroy'])
        ->names('admin.photo-categories');
    Route::get('/admin/photos/import', [PhotoController::class, 'create'])->name('admin.photos.import');
    Route::post('/admin/photos/bulk', [PhotoController::class, 'bulk'])->name('admin.photos.bulk');
    Route::post('/admin/photos/{photo}/process', [PhotoController::class, 'process'])->name('admin.photos.process');
    Route::post('/admin/photos/{photo}/publish', [PhotoController::class, 'publish'])->name('admin.photos.publish');
    Route::post('/admin/photos/{photo}/unpublish', [PhotoController::class, 'unpublish'])->name('admin.photos.unpublish');
    Route::post('/admin/photos/{photo}/set-cover', [PhotoController::class, 'setCover'])->name('admin.photos.set-cover');
    Route::delete('/admin/photos/{photo}/unset-cover', [PhotoController::class, 'unsetCover'])->name('admin.photos.unset-cover');
    Route::resource('/admin/photos', PhotoController::class)
        ->except(['create', 'edit'])
        ->names('admin.photos');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
