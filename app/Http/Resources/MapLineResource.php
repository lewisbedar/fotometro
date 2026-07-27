<?php

namespace App\Http\Resources;

use App\Enums\CoverageStatus;
use App\Services\Map\LineTopologyBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'text_color' => $this->text_color,
            'station_count' => $this->whenCounted('stations'),
            'url' => route('lines.show', $this->slug),
            'path_geojson' => $this->path_geojson,
            'topology' => app(LineTopologyBuilder::class)->build($this->resource),
            'progress' => $this->whenLoaded('stations', function () {
                $total = $this->stations->count();
                $documented = $this->stations
                    ->whereIn('coverage_status', [CoverageStatus::Documented, CoverageStatus::Complete])
                    ->count();

                return [
                    'total' => $total,
                    'documented' => $documented,
                    'in_progress' => $this->stations->where('coverage_status', CoverageStatus::InProgress)->count(),
                    'not_started' => $this->stations->where('coverage_status', CoverageStatus::NotStarted)->count(),
                    'percentage' => $total === 0 ? 0 : (int) round(($documented / $total) * 100),
                ];
            }),
            'branches' => $this->whenLoaded('stations', fn () => $this->lineStationsPayload()
                ->groupBy(fn (array $station) => $station['branch'] ?? 'main')
                ->map(fn ($stations, string $branch) => [
                    'key' => $branch,
                    'label' => $branch === 'main' ? 'Tronc commun' : $branch,
                    'stations' => $stations->values()->all(),
                ])
                ->values()
                ->all(), []),
            'stations' => $this->whenLoaded('stations', fn () => $this->lineStationsPayload()->values()->all(), []),
        ];
    }

    private function lineStationsPayload()
    {
        return $this->stations
            ->map(function ($station) {
                    $status = $station->coverage_status instanceof CoverageStatus
                        ? $station->coverage_status
                        : CoverageStatus::from($station->coverage_status);

                    return [
                        'id' => $station->id,
                        'external_id' => $station->external_id,
                        'name' => $station->name,
                        'slug' => $station->slug,
                        'latitude' => $station->latitude === null ? null : (float) $station->latitude,
                        'longitude' => $station->longitude === null ? null : (float) $station->longitude,
                        'coordinates' => $station->latitude === null || $station->longitude === null
                            ? null
                            : [(float) $station->longitude, (float) $station->latitude],
                        'position' => (int) $station->pivot->position,
                        'branch' => $station->pivot->branch,
                        'is_terminus' => (bool) $station->pivot->is_terminus,
                        'access_count' => $station->accesses_count,
                        'coverage_status' => [
                            'value' => $status->value,
                            'label' => $status->label(),
                            'description' => $status->description(),
                            'color' => $status->color(),
                        ],
                        'connections' => $station->lines
                            ->reject(fn ($line) => $line->id === $this->id)
                            ->map(fn ($line) => [
                                'id' => $line->id,
                                'code' => $line->code,
                                'name' => $line->name,
                                'slug' => $line->slug,
                                'color' => $line->color,
                                'text_color' => $line->text_color,
                            ])
                            ->values()
                            ->all(),
                        'correspondances' => $station->lines
                            ->reject(fn ($line) => $line->id === $this->id)
                            ->map(fn ($line) => [
                                'id' => $line->id,
                                'external_id' => $line->external_id,
                                'code' => $line->code,
                                'name' => $line->name,
                                'slug' => $line->slug,
                                'color' => $line->color,
                                'text_color' => $line->text_color,
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->sortBy([
                    ['position', 'asc'],
                    ['branch', 'asc'],
                    ['name', 'asc'],
                ]);
    }
}
