<?php

$mapBasemapDriver = env('FOTOMETRO_MAP_BASEMAP_DRIVER', 'raster');

if (! in_array($mapBasemapDriver, ['raster', 'style'], true)) {
    $mapBasemapDriver = 'raster';
}

return [
    'map' => [
        'basemap_driver' => $mapBasemapDriver,
        'style_url' => env('FOTOMETRO_MAP_STYLE_URL'),
        'raster_url' => env('FOTOMETRO_MAP_RASTER_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'raster_tile_size' => blank(env('FOTOMETRO_MAP_RASTER_TILE_SIZE')) ? 256 : (int) env('FOTOMETRO_MAP_RASTER_TILE_SIZE'),
        'attribution' => env('FOTOMETRO_MAP_ATTRIBUTION', '© OpenStreetMap contributors'),
        'cache_ttl' => (int) env('FOTOMETRO_MAP_CACHE_TTL', 300),
        'center' => [
            'latitude' => (float) env('FOTOMETRO_MAP_CENTER_LATITUDE', 48.8566),
            'longitude' => (float) env('FOTOMETRO_MAP_CENTER_LONGITUDE', 2.3522),
            'zoom' => (float) env('FOTOMETRO_MAP_DEFAULT_ZOOM', 11.5),
            'max_zoom' => blank(env('FOTOMETRO_MAP_MAX_ZOOM')) ? 19 : (float) env('FOTOMETRO_MAP_MAX_ZOOM'),
        ],
    ],
];
