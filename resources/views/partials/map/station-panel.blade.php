<aside class="map-context-panel map-glass" x-show="selectedStation" x-transition x-cloak aria-labelledby="station-panel-title">
    <template x-if="selectedStation">
        <div>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="station-panel-title" class="text-2xl font-semibold" x-text="selectedStation.name"></h2>
                    <p class="mt-1 text-sm text-black/60" x-text="selectedStation.district || selectedStation.city || 'Localisation a completer'"></p>
                </div>
                <button type="button" class="map-icon-button" x-on:click="clearStationSelection()" aria-label="Fermer la station">×</button>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <template x-for="line in normalizeLines(selectedStation.lines)" :key="line.id">
                    <span class="line-code" :style="`background:${safeLineColor(line.color)};color:${safeLineColor(line.text_color)}`" x-text="line.code"></span>
                </template>
            </div>

            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt>Statut</dt><dd class="font-semibold" x-text="selectedStation.coverage_status.description"></dd></div>
                <div class="flex justify-between gap-4"><dt>Photographies</dt><dd>Donnée bientôt disponible</dd></div>
            </dl>

            <div class="mt-5 border-t border-black/10 pt-4">
                <h3 class="font-semibold">Entrees et sorties</h3>
                <p class="mt-2 text-sm text-black/60">Donnees bientot disponibles</p>
            </div>

            <a class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-md border border-black/15 bg-white px-4 text-sm font-semibold hover:bg-black hover:text-white" :href="selectedStation.url">Voir la station</a>
        </div>
    </template>
</aside>
