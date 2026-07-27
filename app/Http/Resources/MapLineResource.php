<?php

namespace App\Http\Resources;

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
        ];
    }
}
