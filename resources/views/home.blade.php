<x-layouts.app
    title="fotometro - Carte photographique du metro parisien"
    description="Explorez les stations du metro parisien et leur progression photographique sur fotometro."
    :canonical="route('home')"
    :full-width="true"
    :fullscreen="true"
>
    <section
        class="metro-explorer fullscreen-map-shell"
        data-map-endpoint="{{ route('api.map') }}"
        data-search-endpoint="{{ route('api.map.search') }}"
        data-basemap-driver="{{ $mapConfig['basemap_driver'] }}"
        data-map-style="{{ $mapConfig['style_url'] }}"
        data-raster-url="{{ $mapConfig['raster_url'] }}"
        data-raster-tile-size="{{ $mapConfig['raster_tile_size'] }}"
        data-map-attribution="{{ $mapConfig['attribution'] }}"
        data-map-center-latitude="{{ $mapConfig['center']['latitude'] }}"
        data-map-center-longitude="{{ $mapConfig['center']['longitude'] }}"
        data-map-zoom="{{ $mapConfig['center']['zoom'] }}"
        data-map-max-zoom="{{ $mapConfig['center']['max_zoom'] }}"
        data-app-env="{{ app()->environment() }}"
        x-data="fotometroMapExplorer($el.dataset)"
        x-init="init()"
        x-on:keydown.escape.window="handleEscape()"
        x-on:keydown.slash.window.prevent="focusSearch()"
    >
        <div id="metro-map" class="fullscreen-map-canvas" x-show="hasBasemapConfig"></div>

        <div class="fullscreen-map-config" x-show="! hasBasemapConfig" x-cloak>
            <div class="floating-panel max-w-md p-6 text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Fond de carte absent</p>
                <h2 class="mt-2 text-2xl font-semibold">Configurez MapLibre</h2>
                <p class="mt-3 text-sm leading-6 text-black/65">
                    Renseignez <code>FOTOMETRO_MAP_RASTER_URL</code> ou <code>FOTOMETRO_MAP_STYLE_URL</code> selon le driver choisi.
                </p>
            </div>
        </div>

        @include('partials.map.topbar')
        @include('partials.map.global-progress')
        @include('partials.map.filters-panel')
        @include('partials.map.lines-panel')
        @include('partials.map.station-panel')
        @include('partials.map.line-panel')
        @include('partials.map.line-diagram-svg')

        <div class="absolute inset-x-4 bottom-4 z-50 rounded-lg border border-red-200 bg-white p-4 text-sm text-red-800 shadow-sm md:inset-x-auto md:right-4 md:max-w-md" x-show="mapFatalError && isLocal" x-cloak>
            <p class="font-semibold">Erreur MapLibre</p>
            <p class="mt-1 break-words" x-text="mapFatalError"></p>
        </div>
    </section>
</x-layouts.app>
