<div class="relative">
    <label for="station-search" class="block text-sm font-medium">Rechercher une station</label>
    <input
        id="station-search"
        type="search"
        class="mt-2 w-full rounded-md border border-black/15 bg-white px-3 py-2 outline-none focus:border-black focus:ring-2 focus:ring-black/10"
        placeholder="Châtelet, Nation..."
        x-model.debounce.250ms="searchQuery"
        x-on:input="searchStations()"
        x-on:keydown.arrow-down.prevent="moveSearchFocus(1)"
        x-on:keydown.arrow-up.prevent="moveSearchFocus(-1)"
        x-on:keydown.enter.prevent="chooseFocusedSearchResult()"
        aria-controls="station-search-results"
        autocomplete="off"
    >
    <div id="station-search-results" class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-md bg-white shadow-lg ring-1 ring-black/10" x-show="searchQuery.length >= 2" x-cloak>
        <template x-if="searchResults.length === 0 && ! searchLoading">
            <p class="p-3 text-sm text-black/60">Aucune station trouvée.</p>
        </template>
        <template x-for="(station, index) in searchResults" :key="station.id">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-black/5 focus:bg-black/5"
                x-bind:class="{ 'bg-black/5': focusedSearchIndex === index }"
                x-on:click="selectStation(station.id, true)"
            >
                <span>
                    <span class="block font-medium" x-text="station.name"></span>
                    <span class="block text-xs text-black/55" x-text="station.district || station.city || 'Localisation à compléter'"></span>
                </span>
                <span class="flex shrink-0 gap-1">
                    <template x-for="line in normalizeLines(station.lines)" :key="line.id">
                        <span class="grid h-6 min-w-6 place-items-center rounded-full px-1 text-[11px] font-bold" :style="`background:${line.color};color:${line.text_color}`" x-text="line.code"></span>
                    </template>
                </span>
            </button>
        </template>
    </div>
</div>
