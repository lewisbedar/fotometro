<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Station;
use App\Models\StationAccess;
use Illuminate\Http\JsonResponse;

class PhotoSelectionApiController extends Controller
{
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
                    'name' => $this->accessLabel($access, $index),
                    'reference' => $access->reference,
                    'latitude' => $access->latitude !== null ? (float) $access->latitude : null,
                    'longitude' => $access->longitude !== null ? (float) $access->longitude : null,
                ])
                ->all(),
        ]);
    }

    private function accessLabel(StationAccess $access, int $index): string
    {
        $label = trim((string) ($access->name ?: $access->reference ?: $access->description));

        return $label !== '' ? $label : 'Accès '.($index + 1);
    }
}
