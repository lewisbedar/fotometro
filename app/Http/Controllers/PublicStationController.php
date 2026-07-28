<?php

namespace App\Http\Controllers;

use App\Models\Photo;
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

        // Only used to seed the initial highlighted access on the map/sidebar
        // (e.g. a bookmarked ?access= link); the gallery itself resolves and
        // filters independently inside the StationGallery Livewire component.
        $selectedAccess = $this->selectedAccess($request, $station);

        $allPhotos = $this->publicStationPhotos($station)->get();
        $featuredPhotos = $this->featuredPhotos($station);
        $accessCards = $this->accessCards($station, $allPhotos);
        $summary = $coverage->summarize($station);

        return view('stations.show', [
            'station' => $station,
            'featuredPhotos' => $featuredPhotos,
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

    private function selectedAccess(Request $request, Station $station): ?StationAccess
    {
        if (! $request->filled('access')) {
            return null;
        }

        return $station->accesses
            ->first(fn (StationAccess $access) => (string) $access->id === (string) $request->query('access'));
    }

    /**
     * Up to $limit photos for the hero mosaic: the station's cover photo first
     * (if set), then featured photos, filled out with the next photos in normal
     * gallery order.
     */
    private function featuredPhotos(Station $station, int $limit = 4): Collection
    {
        $cover = $station->cover_photo_id
            ? Photo::query()->publiclyVisible()->whereKey($station->cover_photo_id)->with(['category.parent', 'stationAccess'])->first()
            : null;

        $rest = Photo::query()
            ->publiclyVisible()
            ->where('station_id', $station->id)
            ->when($cover, fn ($query) => $query->whereKeyNot($cover->id))
            ->with(['category.parent', 'stationAccess'])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByRaw('taken_at IS NULL')
            ->orderBy('taken_at')
            ->orderBy('id')
            ->limit($cover ? $limit - 1 : $limit)
            ->get();

        return $cover ? $rest->prepend($cover) : $rest;
    }

    private function accessCards(Station $station, Collection $photos): Collection
    {
        return $station->accesses
            ->sortBy(fn (StationAccess $access) => sprintf(
                '%d-%05d-%s',
                $access->number === null ? 1 : 0,
                (int) ($access->number ?? 0),
                $access->displayName()
            ))
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
                'status_color' => $station->coverage_status->color(),
            ],
            'accesses' => $accessCards
                ->map(fn (array $card) => [
                    'id' => $card['access']->id,
                    'name' => $card['label'],
                    'number' => $card['access']->number,
                    'latitude' => $card['access']->latitude === null ? null : (float) $card['access']->latitude,
                    'longitude' => $card['access']->longitude === null ? null : (float) $card['access']->longitude,
                    'photo_count' => $card['photo_count'],
                ])
                ->values()
                ->all(),
        ];
    }
}
