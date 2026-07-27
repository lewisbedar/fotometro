<?php

namespace App\Http\Resources;

use App\Enums\CoverageStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'text_color' => $this->text_color,
            'station_count' => $this->whenCounted('stations'),
            'url' => route('lines.show', $this->slug),
            'path_geojson' => $this->path_geojson,
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
            'stations' => $this->whenLoaded('stations', fn () => $this->stations
                ->map(function ($station) {
                    $status = $station->coverage_status instanceof CoverageStatus
                        ? $station->coverage_status
                        : CoverageStatus::from($station->coverage_status);

                    return [
                        'id' => $station->id,
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
                    ];
                })
                ->sortBy('position')
                ->values()
                ->all(), []),
        ];
    }
}
