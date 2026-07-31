<div
    class="relative order-3 w-full sm:order-none sm:w-72"
    x-data="fotometroSiteSearch($el.dataset)"
    x-on:click.outside="close()"
    data-search-endpoint="{{ route('api.map.search') }}"
>
    <label for="site-search" class="sr-only">Rechercher une station</label>
    <div class="flex min-h-10 items-center gap-2 rounded-full border border-black/15 bg-white px-3">
        <x-icons.search class="h-4 w-4 shrink-0 text-black/45" />
        <input
            id="site-search"
            type="search"
            class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-black/45"
            placeholder="Rechercher une station..."
            x-model="query"
            x-on:input="queueSearch()"
            x-on:focus="open = query.trim().length >= 2"
            x-on:keydown.escape.stop="close()"
            autocomplete="off"
        >
    </div>

    <div
        x-show="open && query.trim().length >= 2"
        x-cloak
        x-transition
        class="absolute left-0 right-0 top-full z-30 mt-2 max-h-96 overflow-y-auto rounded-lg bg-white py-1.5 text-sm shadow-lg ring-1 ring-black/10"
    >
        <template x-if="loading">
            <p class="px-3 py-2 text-black/60">Recherche...</p>
        </template>
        <template x-if="error && ! loading">
            <p class="px-3 py-2 text-red-700" x-text="error"></p>
        </template>
        <template x-if="! loading && ! error && stations.length === 0">
            <p class="px-3 py-2 text-black/60">Aucun résultat.</p>
        </template>

        <template x-if="stations.length > 0">
            <div>
                <template x-for="station in stations" :key="station.id">
                    <a :href="station.url" class="flex items-center gap-3 px-3 py-2 hover:bg-black/5">
                        <template x-if="station.cover_photo_url">
                            <img :src="station.cover_photo_url" alt="" class="h-9 w-9 shrink-0 rounded object-cover">
                        </template>
                        <template x-if="! station.cover_photo_url">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded bg-black/5 text-black/30">
                                <x-icons.metro class="h-4 w-4" />
                            </span>
                        </template>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium" x-text="station.name"></span>
                            <span class="flex flex-wrap gap-1 pt-0.5">
                                <template x-for="line in station.lines" :key="`${station.id}-${line.id}`">
                                    <span class="grid h-5 min-w-5 place-items-center rounded-full px-1 text-[10px] font-bold" :style="`background:${safeColor(line.color)};color:${safeColor(line.text_color)}`" x-text="line.code"></span>
                                </template>
                            </span>
                        </span>
                    </a>
                </template>
            </div>
        </template>
    </div>
</div>
