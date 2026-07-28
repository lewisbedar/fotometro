<?php

namespace App\Services\Photos;

use App\Models\Photo;
use App\Models\Station;

class StationPhotoCoverageService
{
    public function summarize(Station $station): array
    {
        $publicPhotos = Photo::query()
            ->publiclyVisible()
            ->where('station_id', $station->id);

        $lastPhoto = (clone $publicPhotos)
            ->orderByRaw('COALESCE(taken_at, published_at, created_at) DESC')
            ->first(['id', 'taken_at', 'published_at', 'created_at']);

        return [
            'total_photos' => (clone $publicPhotos)->count(),
            'represented_categories' => (clone $publicPhotos)->whereNotNull('photo_category_id')->distinct('photo_category_id')->count('photo_category_id'),
            'total_accesses' => $station->accesses()->where('station_accesses.is_active', true)->count(),
            'photographed_accesses' => (clone $publicPhotos)->whereNotNull('station_access_id')->distinct('station_access_id')->count('station_access_id'),
            'last_photo_at' => $lastPhoto?->taken_at ?? $lastPhoto?->published_at ?? $lastPhoto?->created_at,
        ];
    }
}
