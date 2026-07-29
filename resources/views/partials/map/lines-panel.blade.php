<aside id="lines-panel" class="map-lines-panel map-glass" x-show="isLinesOpen" x-transition x-cloak aria-labelledby="lines-title">
    <div class="flex items-center justify-between">
        <h2 id="lines-title" class="text-base font-semibold">Lignes</h2>
        <button type="button" class="map-icon-button inline-flex items-center justify-center" x-on:click="isLinesOpen = false" aria-label="Fermer les lignes"><x-icons.close class="h-4 w-4" /></button>
    </div>
    <div class="mt-4 grid gap-2">
        <template x-for="line in mapData.lines" :key="line.id">
            <button type="button" class="line-select-button" x-on:click="selectLine(line.id)" x-bind:class="{ 'is-active': selectedLineId === line.id }">
                <span class="line-code" :style="`background:${safeLineColor(line.color)};color:${safeLineColor(line.text_color)}`" x-text="line.code"></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block text-sm font-semibold"><span x-text="line.station_count ?? line.stations?.length ?? 0"></span> stations</span>
                    <span class="block text-xs text-black/55"><span x-text="line.progress?.percentage ?? 0"></span>% de couverture</span>
                </span>
            </button>
        </template>
    </div>
</aside>
