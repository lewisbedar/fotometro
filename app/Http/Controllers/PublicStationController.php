<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\Station;
use App\Models\StationAccess;
use App\Services\Photos\StationPhotoCoverageService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicStationController extends Controller
{
    public function show(Request $request, Station $station, StationPhotoCoverageService $coverage): View
    {
        abort_unless($station->is_active, 404);

        $station->load([
            'lines' => fn ($query) => $query->orderBy('sort_order'),
            'accesses' => fn ($query) => $query
                ->where('station_accesses.is_active', true)
                ->orderByRaw("COALESCE(NULLIF(station_accesses.name, ''), NULLIF(station_accesses.reference, ''), NULLIF(station_accesses.description, ''), '')")
                ->orderBy('station_accesses.id'),
        ]);

        $selectedCategory = $this->selectedCategory($request);
        $selectedAccess = $this->selectedAccess($request, $station);
        $galleryQuery = $this->publicStationPhotos($station)
            ->when($selectedCategory, fn ($query) => $this->applyCategoryFilter($query, $selectedCategory))
            ->when($selectedAccess, fn ($query) => $query->where('station_access_id', $selectedAccess->id));

        $photos = $galleryQuery
            ->paginate(24)
            ->withQueryString();

        $allPhotos = $this->publicStationPhotos($station)->get();
        $featuredPhoto = $this->featuredPhoto($station);
        $categoryFilters = $this->categoryFilters($allPhotos);
        $subCategoryFilters = $selectedCategory && $selectedCategory->parent_id === null
            ? $this->subCategoryFilters($allPhotos, $selectedCategory)
            : collect();
        $accessCards = $this->accessCards($station, $allPhotos);
        $summary = $coverage->summarize($station);

        return view('stations.show', [
            'station' => $station,
            'photos' => $photos,
            'featuredPhoto' => $featuredPhoto,
            'categoryFilters' => $categoryFilters,
            'subCategoryFilters' => $subCategoryFilters,
            'selectedCategory' => $selectedCategory,
            'selectedAccess' => $selectedAccess,
            'accessCards' => $accessCards,
            'coverageSummary' => $summary,
            'accessMapPayload' => $this->accessMapPayload($station, $accessCards),
            'mapConfig' => config('fotometro.map'),
            'metaDescription' => "Découvrez les photographies de la station {$station->name} : quais, accès, architecture, signalétique et détails du métro parisien.",
        ]);
    }

    private function publicStationPhotos(Station $station)
    {
        return Photo::query()
            ->publiclyVisible()
            ->where('station_id', $station->id)
            ->with(['category.parent', 'stationAccess'])
            ->orderBy('sort_order')
            ->orderByRaw('taken_at IS NULL')
            ->orderBy('taken_at')
            ->orderBy('id');
    }

    private function selectedCategory(Request $request): ?PhotoCategory
    {
        if (! $request->filled('category')) {
            return null;
        }

        return PhotoCategory::query()
            ->where('slug', $request->query('category'))
            ->where('is_active', true)
            ->first();
    }

    private function selectedAccess(Request $request, Station $station): ?StationAccess
    {
        if (! $request->filled('access')) {
            return null;
        }

        return $station->accesses
            ->first(fn (StationAccess $access) => (string) $access->id === (string) $request->query('access'));
    }

    private function applyCategoryFilter($query, PhotoCategory $category): void
    {
        if ($category->parent_id !== null) {
            $query->where('photo_category_id', $category->id);
            return;
        }

        $childIds = $category->children()->pluck('id')->all();
        $query->whereIn('photo_category_id', [$category->id, ...$childIds]);
    }

    private function featuredPhoto(Station $station): ?Photo
    {
        return Photo::query()
            ->publiclyVisible()
            ->where('station_id', $station->id)
            ->with(['category.parent', 'stationAccess'])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByRaw('taken_at IS NULL')
            ->orderBy('taken_at')
            ->orderBy('id')
            ->first();
    }

    private function categoryFilters(Collection $photos): Collection
    {
        return $photos
            ->map(fn (Photo $photo) => $photo->category?->parent ?: $photo->category)
            ->filter()
            ->groupBy('id')
            ->map(fn (Collection $categories, int $id) => [
                'category' => $categories->first(),
                'count' => $photos->filter(function (Photo $photo) use ($id): bool {
                    return $photo->category?->id === $id || $photo->category?->parent_id === $id;
                })->count(),
            ])
            ->sortBy(fn (array $item) => [$item['category']->sort_order, $item['category']->name])
            ->values();
    }

    private function subCategoryFilters(Collection $photos, PhotoCategory $root): Collection
    {
        return $photos
            ->map(fn (Photo $photo) => $photo->category)
            ->filter(fn (?PhotoCategory $category) => $category && (int) $category->parent_id === (int) $root->id)
            ->groupBy('id')
            ->map(fn (Collection $categories) => [
                'category' => $categories->first(),
                'count' => $categories->count(),
            ])
            ->sortBy(fn (array $item) => [$item['category']->sort_order, $item['category']->name])
            ->values();
    }

    private function accessCards(Station $station, Collection $photos): Collection
    {
        return $station->accesses
            ->values()
            ->map(function (StationAccess $access, int $index) use ($photos): array {
                $accessPhotos = $photos
                    ->where('station_access_id', $access->id)
                    ->values();

                return [
                    'access' => $access,
                    'label' => $access->displayName($index),
                    'photo_count' => $accessPhotos->count(),
                    'preview_photos' => $accessPhotos->take(3)->values(),
                ];
            });
    }

    private function accessMapPayload(Station $station, Collection $accessCards): array
    {
        return [
            'station' => [
                'id' => $station->id,
                'name' => $station->name,
                'latitude' => $station->latitude === null ? null : (float) $station->latitude,
                'longitude' => $station->longitude === null ? null : (float) $station->longitude,
            ],
            'accesses' => $accessCards
                ->map(fn (array $card) => [
                    'id' => $card['access']->id,
                    'name' => $card['label'],
                    'latitude' => $card['access']->latitude === null ? null : (float) $card['access']->latitude,
                    'longitude' => $card['access']->longitude === null ? null : (float) $card['access']->longitude,
                    'photo_count' => $card['photo_count'],
                ])
                ->values()
                ->all(),
        ];
    }
}
