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
    'idfm' => [
        'arrets_lignes_url' => env('FOTOMETRO_IDFM_ARRETS_LIGNES_URL', 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/arrets-lignes/records?limit=100'),
        'stop_areas_url' => env('FOTOMETRO_IDFM_STOP_AREAS_URL', 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/zones-d-arrets/exports/csv?limit=-1'),
        'stop_relations_url' => env('FOTOMETRO_IDFM_STOP_RELATIONS_URL', 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/relations/exports/csv?limit=-1'),
        'traces_url' => env('FOTOMETRO_IDFM_TRACES_URL', 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/traces-des-lignes-de-transport-en-commun-idfm/records?limit=100'),
        'accesses_url' => env('FOTOMETRO_IDFM_ACCESSES_URL', 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/acces/exports/csv?limit=-1'),
        'access_relations_url' => env('FOTOMETRO_IDFM_ACCESS_RELATIONS_URL', 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/relations-acces/exports/csv?limit=-1'),
        'timeout' => (int) env('FOTOMETRO_IDFM_TIMEOUT', 30),
        'temp_dir' => env('FOTOMETRO_IDFM_TEMP_DIR', storage_path('app/idfm')),
        'import_accesses' => filter_var(env('FOTOMETRO_IDFM_IMPORT_ACCESSES', true), FILTER_VALIDATE_BOOL),
        'deactivate_absent' => filter_var(env('FOTOMETRO_IDFM_DEACTIVATE_ABSENT', true), FILTER_VALIDATE_BOOL),
    ],
];
