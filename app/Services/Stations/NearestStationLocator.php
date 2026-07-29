<?php

namespace App\Services\Stations;

use App\Models\Station;

class NearestStationLocator
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    /**
     * @return array{station: Station, distance_meters: float}|null
     */
    public function locate(float $latitude, float $longitude): ?array
    {
        $radius = (float) config('fotometro.photos.exif_station_match_radius_meters', 200);

        $nearest = null;
        $nearestDistance = null;

        Station::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->each(function (Station $station) use ($latitude, $longitude, &$nearest, &$nearestDistance): void {
                $distance = $this->haversineMeters($latitude, $longitude, (float) $station->latitude, (float) $station->longitude);

                if ($nearestDistance === null || $distance < $nearestDistance) {
                    $nearest = $station;
                    $nearestDistance = $distance;
                }
            });

        if ($nearest === null || $nearestDistance > $radius) {
            return null;
        }

        return ['station' => $nearest, 'distance_meters' => round($nearestDistance, 1)];
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }
}
