<?php

namespace App\Http\Resources;

use App\Enums\CoverageStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapStationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->coverage_status instanceof CoverageStatus
            ? $this->coverage_status
            : CoverageStatus::from($this->coverage_status);

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'coordinates' => $this->latitude === null || $this->longitude === null
                ? null
                : [(float) $this->longitude, (float) $this->latitude],
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'district' => $this->district,
            'coverage_status' => [
                'value' => $status->value,
                'label' => $status->label(),
                'description' => $status->description(),
                'color' => $status->color(),
            ],
            'coverage_percentage' => $this->coverage_percentage,
            'cover_photo_url' => $this->whenLoaded('coverPhoto', fn () => $this->coverPhoto?->thumbnail_url),
            'url' => route('stations.show', $this->slug),
            'access_count' => $this->accesses_count,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines
                ->map(fn ($line) => [
                    'id' => $line->id,
                    'external_id' => $line->external_id,
                    'code' => $line->code,
                    'name' => $line->name,
                    'slug' => $line->slug,
                    'color' => $line->color,
                    'text_color' => $line->text_color,
                    'url' => route('lines.show', $line->slug),
                ])
                ->values()
                ->all(), []),
        ];
    }
}
