<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Admin\PhotoCategoryController;
use App\Http\Controllers\Admin\PhotoController;
use App\Http\Controllers\Admin\PhotoRejectionReasonController;
use App\Http\Controllers\Admin\PhotoSelectionApiController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Photos\PhotoUploadController;
use App\Livewire\PhotoModerationQueue;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapDataController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicPhotoController;
use App\Http\Controllers\PublicLineController;
use App\Http\Controllers\PublicStationController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StationSearchController;
use App\Http\Resources\MapLineResource;
use App\Services\Photos\AuthShowcasePhoto;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', HomeController::class)->name('home');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', function () {
    return response(implode("\n", [
        'User-agent: *',
        'Disallow: /admin',
        'Disallow: /login',
        'Disallow: /inscription',
        'Disallow: /parametres',
        'Disallow: /televerser',
        'Disallow: /mot-de-passe-oublie',
        'Disallow: /reinitialiser-mot-de-passe',
        'Disallow: /api/',
        '',
        'Sitemap: '.route('sitemap'),
    ]), 200, ['Content-Type' => 'text/plain']);
})->name('robots');
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
Route::get('/profil/{user:username}', [ProfileController::class, 'show'])->name('profiles.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('/inscription', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisteredUserController::class, 'store'])->middleware('throttle:register')->name('register.store');
    Route::get('/inscription/en-attente', fn (AuthShowcasePhoto $showcasePhoto) => view('auth.register-pending', ['showcasePhoto' => $showcasePhoto->random()]))->name('register.pending');

    Route::get('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reinitialiser-mot-de-passe/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reinitialiser-mot-de-passe', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

// Shared by every approved account regardless of role.
Route::middleware(['auth', 'approved'])->group(function (): void {
    Route::get('/admin/api/lines/{line}/stations', [PhotoSelectionApiController::class, 'stations'])->name('admin.api.lines.stations');
    Route::get('/admin/api/stations/{station}/accesses', [PhotoSelectionApiController::class, 'accesses'])->name('admin.api.stations.accesses');
    Route::post('/admin/photos/detect-station', [PhotoSelectionApiController::class, 'detectStation'])->name('admin.api.photos.detect-station');

    Route::get('/televerser', [PhotoUploadController::class, 'create'])->name('photos.upload.create');
    Route::post('/televerser', [PhotoUploadController::class, 'store'])->name('photos.upload.store');
    Route::get('/televerser/merci', fn () => view('photos.upload-thanks'))->name('photos.upload.thanks');

    // No {user} route parameter on purpose: these always act on the
    // authenticated user, never on an arbitrary profile passed in the URL.
    Route::patch('/mon-profil/bio', [ProfileController::class, 'updateBio'])->name('profile.bio.update');
    Route::post('/mon-profil/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/mon-profil/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    Route::get('/parametres', [AccountSettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/parametres', [AccountSettingsController::class, 'update'])->name('settings.update');
    Route::delete('/parametres', [AccountSettingsController::class, 'destroy'])->name('settings.destroy');
});

Route::middleware(['auth', 'approved', 'role:admin,moderator'])->group(function (): void {
    Route::get('/admin', DashboardController::class)->name('admin.dashboard');
    Route::get('/admin/moderation', PhotoModerationQueue::class)->name('admin.moderation.index');
});

Route::middleware(['auth', 'approved', 'role:admin'])->group(function (): void {
    Route::post('/admin/photo-categories/reorder', [PhotoCategoryController::class, 'reorder'])->name('admin.photo-categories.reorder');
    Route::resource('/admin/photo-categories', PhotoCategoryController::class)
        ->except(['show'])
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

    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::patch('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/users/{user}/approve', [UserController::class, 'approve'])->name('admin.users.approve');
    Route::post('/admin/users/{user}/reject', [UserController::class, 'reject'])->name('admin.users.reject');

    Route::resource('/admin/photo-rejection-reasons', PhotoRejectionReasonController::class)
        ->except(['show', 'destroy'])
        ->names('admin.photo-rejection-reasons');
});
