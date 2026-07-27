<x-layouts.app
    title="fotometro - Carte photographique du métro parisien"
    description="Explorez les stations du métro parisien et leur progression photographique sur fotometro."
    :canonical="route('home')"
    :full-width="true"
>
    <section
        class="metro-explorer"
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
        x-on:keydown.escape.window="clearSelection()"
    >
        <div class="grid min-h-[calc(100vh-9rem)] gap-5 lg:grid-cols-[380px_minmax(0,1fr)]">
            <aside class="hidden overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5 lg:flex lg:flex-col">
                <div class="space-y-4 border-b border-black/10 p-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">fotometro</p>
                        <h1 class="mt-2 text-2xl font-semibold">Catalogue photographique des stations du métro parisien</h1>
                    </div>
                    @include('partials.map-search')
                </div>

                <div class="flex-1 overflow-y-auto p-5">
                    @include('partials.map-controls')
                </div>
            </aside>

            <div class="flex flex-col gap-4">
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-black/5 lg:hidden">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">fotometro</p>
                            <h1 class="mt-1 text-xl font-semibold">Carte du métro parisien</h1>
                        </div>
                        <button type="button" class="rounded-md border border-black/15 px-3 py-2 text-sm font-medium" x-on:click="toggleMobilePanel()" x-bind:aria-expanded="mobilePanelOpen.toString()">
                            Filtres
                        </button>
                    </div>
                    <div class="mt-4" x-show="mobilePanelOpen" x-cloak>
                        @include('partials.map-search')
                        <div class="mt-5">
                            @include('partials.map-controls')
                        </div>
                    </div>
                </div>

                <div class="relative min-h-[68vh] overflow-hidden rounded-lg bg-[#e9e1d2] shadow-sm ring-1 ring-black/5 lg:min-h-[calc(100vh-9rem)]">
                    <div id="metro-map" class="h-full min-h-[68vh] w-full lg:min-h-[calc(100vh-9rem)]" x-show="hasBasemapConfig"></div>

                    <div class="absolute inset-0 grid place-items-center p-6 text-center" x-show="! hasBasemapConfig">
                        <div class="max-w-md rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/10">
                            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Fond de carte absent</p>
                            <h2 class="mt-2 text-2xl font-semibold">Configurez MapLibre</h2>
                            <p class="mt-3 text-sm leading-6 text-black/65">
                                Renseignez <code>FOTOMETRO_MAP_RASTER_URL</code> ou <code>FOTOMETRO_MAP_STYLE_URL</code> selon le driver choisi. Les stations, lignes et fiches restent disponibles.
                            </p>
                        </div>
                    </div>

                    <div class="absolute inset-x-4 bottom-4 rounded-lg border border-red-200 bg-white p-4 text-sm text-red-800 shadow-sm" x-show="mapFatalError && isLocal" x-cloak>
                        <p class="font-semibold">Erreur MapLibre</p>
                        <p class="mt-1 break-words" x-text="mapFatalError"></p>
                    </div>

                    <div class="absolute left-4 top-4 max-w-sm rounded-lg bg-white/95 p-4 shadow-sm ring-1 ring-black/10" x-show="selectedStation" x-cloak>
                        <button type="button" class="float-right rounded-full border border-black/15 px-2 text-sm" x-on:click="clearSelection()" aria-label="Fermer la station sélectionnée">×</button>
                        <template x-if="selectedStation">
                            <div class="space-y-3 pr-6">
                                <h2 class="text-xl font-semibold" x-text="selectedStation.name"></h2>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="line in normalizeLines(selectedStation.lines)" :key="line.id">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold" :style="`background:${line.color};color:${line.text_color}`" x-text="line.code"></span>
                                    </template>
                                </div>
                                <p class="text-sm text-black/65" x-text="selectedStation.district || selectedStation.city || 'Localisation à compléter'"></p>
                                <p class="text-sm font-medium" x-text="selectedStation.coverage_status.description"></p>
                                <a class="inline-flex rounded-md bg-[#151515] px-3 py-2 text-sm font-semibold text-white" :href="selectedStation.url">Voir la station</a>
                            </div>
                        </template>
                    </div>
                </div>

                <footer class="text-xs leading-5 text-black/55">
                    Données de démonstration non officielles. <span x-text="mapAttribution || 'Crédits cartographiques à configurer selon le style MapLibre choisi.'"></span>
                </footer>
            </div>
        </div>
    </section>
</x-layouts.app>
