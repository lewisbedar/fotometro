<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Station;
use App\Models\StationAccess;
use App\Services\Photos\ExifReader;
use App\Services\Stations\NearestStationLocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoSelectionApiController extends Controller
{
    public function detectStation(Request $request, ExifReader $exif, NearestStationLocator $locator): JsonResponse
    {
        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:'.((int) config('fotometro.photos.max_upload_mb', 40) * 1024),
            ],
        ]);

        $metadata = $exif->read($data['file']->getRealPath());
        $latitude = $metadata['latitude'] ?? null;
        $longitude = $metadata['longitude'] ?? null;

        if ($latitude === null || $longitude === null) {
            return response()->json(['matched' => false]);
        }

        $match = $locator->locate($latitude, $longitude);

        if ($match === null) {
            return response()->json(['matched' => false]);
        }

        $station = $match['station'];
        $station->loadMissing(['lines' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')]);
        $line = $station->lines->first();

        if ($line === null) {
            return response()->json(['matched' => false]);
        }

        return response()->json([
            'matched' => true,
            'distance_meters' => $match['distance_meters'],
            'station' => ['id' => $station->id, 'name' => $station->name],
            'line' => ['id' => $line->id, 'name' => $line->name],
        ]);
    }

    public function stations(Line $line): JsonResponse
    {
        $line->load(['stations' => fn ($query) => $query
            ->where('is_active', true)
            ->with(['lines' => fn ($lineQuery) => $lineQuery->where('is_active', true)->orderBy('sort_order')])
            ->orderByPivot('position')
            ->orderBy('name')]);

        return response()->json([
            'data' => $line->stations
                ->values()
                ->map(fn (Station $station) => [
                    'id' => $station->id,
                    'name' => $station->name,
                    'slug' => $station->slug,
                    'latitude' => $station->latitude !== null ? (float) $station->latitude : null,
                    'longitude' => $station->longitude !== null ? (float) $station->longitude : null,
                    'position' => $station->pivot?->position,
                    'branch' => $station->pivot?->branch,
                    'is_terminus' => (bool) $station->pivot?->is_terminus,
                    'lines' => $station->lines
                        ->values()
                        ->map(fn (Line $connection) => [
                            'id' => $connection->id,
                            'code' => $connection->code,
                            'name' => $connection->name,
                            'slug' => $connection->slug,
                            'color' => $connection->color,
                            'text_color' => $connection->text_color,
                        ])
                        ->all(),
                ])
                ->all(),
        ]);
    }

    public function accesses(Station $station): JsonResponse
    {
        $accesses = $station->accesses()
            ->where('station_accesses.is_active', true)
            ->orderByRaw("COALESCE(NULLIF(station_accesses.name, ''), NULLIF(station_accesses.reference, ''), NULLIF(station_accesses.description, ''), '')")
            ->orderBy('station_accesses.id')
            ->get();

        return response()->json([
            'data' => $accesses
                ->values()
                ->map(fn (StationAccess $access, int $index) => [
                    'id' => $access->id,
                    'name' => $access->displayName($index),
                    'number' => $access->number,
                    'reference' => $access->reference,
                    'latitude' => $access->latitude !== null ? (float) $access->latitude : null,
                    'longitude' => $access->longitude !== null ? (float) $access->longitude : null,
                ])
                ->all(),
        ]);
    }

}
