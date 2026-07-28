<header class="fullscreen-map-topbar" aria-label="Navigation cartographique">
    <button type="button" class="map-glass map-logo-block" x-on:click="resetExplorer()" aria-label="Réinitialiser la carte">
        <x-fotometro-logo :href="null" />
    </button>

    <div class="map-glass map-search-block">
        <label for="station-search" class="sr-only">Rechercher une station</label>
        <div class="flex min-h-11 items-center gap-3">
            <span aria-hidden="true" class="text-lg">⌕</span>
            <input
                id="station-search"
                x-ref="searchInput"
                type="search"
                class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-black/50"
                placeholder="Rechercher une station..."
                x-model="searchQuery"
                x-on:input="queueSearch()"
                x-on:focus="activePanel = 'search'"
                x-on:keydown.arrow-down.prevent="moveSearchFocus(1)"
                x-on:keydown.arrow-up.prevent="moveSearchFocus(-1)"
                x-on:keydown.enter.prevent="chooseFocusedSearchResult()"
                x-on:keydown.escape.stop="closeSearch()"
                aria-controls="station-search-results"
                autocomplete="off"
            >
            <kbd class="hidden rounded border border-black/10 px-2 py-1 text-xs text-black/55 sm:block">/</kbd>
        </div>

        <div id="station-search-results" class="map-search-results" x-show="searchQuery.length >= 2 && activePanel === 'search'" x-cloak>
            <template x-if="searchLoading">
                <p class="p-3 text-sm text-black/60">Recherche...</p>
            </template>
            <template x-if="searchError && ! searchLoading">
                <p class="p-3 text-sm text-red-700" x-text="searchError"></p>
            </template>
            <template x-if="searchResults.length === 0 && searchLineResults.length === 0 && ! searchLoading && ! searchError">
                <p class="p-3 text-sm text-black/60">Aucune station trouvée.</p>
            </template>
            <template x-if="searchLineResults.length > 0">
                <div>
                    <p class="px-3 pt-3 text-xs font-semibold uppercase tracking-[0.16em] text-black/45">Lignes</p>
                    <template x-for="line in searchLineResults" :key="`line-${line.id}`">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-black/5 focus:bg-black/5 focus:outline-none"
                            x-on:click="selectSearchLine(line.id)"
                        >
                            <x-icons.metro class="h-5 w-5 shrink-0 text-black/65" />
                            <span class="grid h-7 min-w-7 place-items-center rounded-full px-1 text-xs font-bold" :style="`background:${safeLineColor(line.color)};color:${safeLineColor(line.text_color)}`" x-text="line.code"></span>
                            <span>
                                <span class="block font-medium" x-text="line.name"></span>
                                <span class="block text-xs text-black/55"><span x-text="line.station_count ?? 0"></span> stations</span>
                            </span>
                        </button>
                    </template>
                </div>
            </template>
            <template x-if="searchResults.length > 0">
                <p class="px-3 pt-3 text-xs font-semibold uppercase tracking-[0.16em] text-black/45">Stations</p>
            </template>
            <template x-for="(station, index) in searchResults" :key="station.id">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-black/5 focus:bg-black/5 focus:outline-none"
                    x-bind:class="{ 'bg-black/5': focusedSearchIndex === index }"
                    x-on:click="selectStation(station.id, true)"
                >
                    <span>
                        <span class="block font-medium" x-text="station.name"></span>
                        <span class="block text-xs text-black/55" x-text="station.district || station.city || 'Localisation à compléter'"></span>
                    </span>
                    <span class="flex shrink-0 gap-1">
                        <template x-for="line in normalizeLines(station.lines)" :key="line.id">
                            <span class="grid h-6 min-w-6 place-items-center rounded-full px-1 text-[11px] font-bold" :style="`background:${safeLineColor(line.color)};color:${safeLineColor(line.text_color)}`" x-text="line.code"></span>
                        </template>
                    </span>
                </button>
            </template>
        </div>
    </div>

    <nav class="map-top-actions" aria-label="Actions de carte">
        <button type="button" class="map-glass map-action-button" x-on:click="toggleLinesPanel()" x-bind:aria-expanded="isLinesOpen.toString()" aria-controls="lines-panel">
            <span aria-hidden="true">⌘</span>
            <span>Lignes</span>
        </button>
        <button type="button" class="map-glass map-action-button" x-on:click="toggleFiltersPanel()" x-bind:aria-expanded="isFiltersOpen.toString()" aria-controls="filters-panel">
            <span aria-hidden="true">☷</span>
            <span>Filtres</span>
        </button>
        <a href="{{ auth()->check() ? route('admin.dashboard') : route('login') }}" class="map-glass map-action-button">
            <span aria-hidden="true">♙</span>
            <span>Administration</span>
        </a>
    </nav>
</header>
