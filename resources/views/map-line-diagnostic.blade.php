<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnostic lignes MapLibre - fotometro</title>
    @vite(['resources/css/app.css', 'resources/js/map-line-diagnostic.js'])
</head>
<body class="bg-white text-black">
    <main
        id="map-line-diagnostic"
        data-map-endpoint="{{ route('api.map') }}"
        data-basemap-driver="{{ $mapConfig['basemap_driver'] ?? config('fotometro.map.basemap_driver') }}"
        data-map-style="{{ $mapConfig['style_url'] ?? config('fotometro.map.style_url') }}"
        data-raster-url="{{ $mapConfig['raster_url'] ?? config('fotometro.map.raster_url') }}"
        data-raster-tile-size="{{ $mapConfig['raster_tile_size'] ?? config('fotometro.map.raster_tile_size') }}"
        data-map-attribution="{{ $mapConfig['attribution'] ?? config('fotometro.map.attribution') }}"
        data-map-center-latitude="{{ $mapConfig['center']['latitude'] ?? config('fotometro.map.center.latitude') }}"
        data-map-center-longitude="{{ $mapConfig['center']['longitude'] ?? config('fotometro.map.center.longitude') }}"
        data-map-zoom="{{ $mapConfig['center']['zoom'] ?? config('fotometro.map.center.zoom') }}"
        data-map-max-zoom="{{ $mapConfig['center']['max_zoom'] ?? config('fotometro.map.center.max_zoom') }}"
    >
        <section class="fixed left-4 top-4 z-10 max-w-md rounded-lg bg-white/95 p-4 text-sm shadow">
            <h1 class="font-semibold">Diagnostic lignes MapLibre</h1>
            <p class="mt-1 text-black/65">Page locale isolée : pas d’Alpine, pas de Livewire, pas de panneaux.</p>
            <p class="mt-2 font-mono text-xs" id="map-line-diagnostic-status">Initialisation...</p>
        </section>
        <div id="map-line-diagnostic-canvas" class="fixed inset-0" style="position: fixed; inset: 0; height: 100dvh; width: 100%;"></div>
    </main>
</body>
</html>
