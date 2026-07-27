<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapDataController;
use App\Http\Controllers\PublicLineController;
use App\Http\Controllers\PublicStationController;
use App\Http\Controllers\StationSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/map-diagnostic', fn () => app()->isLocal()
    ? view('map-diagnostic')
    : abort(404))->name('map.diagnostic');
Route::get('/api/map', MapDataController::class)->name('api.map');
Route::get('/api/map/search', StationSearchController::class)->middleware('throttle:30,1')->name('api.map.search');
Route::get('/stations/{station:slug}', [PublicStationController::class, 'show'])->name('stations.show');
Route::get('/lignes/{line:slug}', [PublicLineController::class, 'show'])->name('lines.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/admin', DashboardController::class)->name('admin.dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
