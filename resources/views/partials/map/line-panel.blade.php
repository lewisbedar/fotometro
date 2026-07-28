<aside class="map-context-panel map-glass" x-show="selectedLine" x-transition x-cloak :aria-label="`Ligne ${selectedLine?.code ?? ''}`">
    <template x-if="selectedLine">
        <div>
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="line-code h-12 min-w-12 text-base" :style="`background:${safeLineColor(selectedLine.color)};color:${safeLineColor(selectedLine.text_color)}`" x-text="selectedLine.code"></span>
                    <h2 class="text-lg font-semibold leading-snug" x-text="lineTerminusLabel(selectedLine)"></h2>
                </div>
                <button type="button" class="map-icon-button" x-on:click="clearLineSelection()" aria-label="Fermer la ligne">×</button>
            </div>
            <dl class="mt-5 space-y-3 text-sm" x-show="! isLineDiagramOpen">
                <div class="flex justify-between gap-4"><dt>Stations</dt><dd class="font-semibold" x-text="selectedLine.progress?.total ?? selectedLine.stations?.length ?? 0"></dd></div>
                <div class="flex justify-between gap-4"><dt>Documentées</dt><dd class="font-semibold" x-text="selectedLine.progress?.documented ?? 0"></dd></div>
                <div class="flex justify-between gap-4"><dt>En cours</dt><dd class="font-semibold" x-text="selectedLine.progress?.in_progress ?? 0"></dd></div>
                <div class="flex justify-between gap-4"><dt>Non commencées</dt><dd class="font-semibold" x-text="selectedLine.progress?.not_started ?? 0"></dd></div>
                <div class="flex justify-between gap-4"><dt>Progression</dt><dd class="font-semibold"><span x-text="selectedLine.progress?.percentage ?? 0"></span>%</dd></div>
            </dl>
            <a class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-md border border-black/15 bg-white px-4 text-sm font-semibold hover:bg-black hover:text-white" :href="selectedLine.url">Voir la ligne</a>
        </div>
    </template>
</aside>
