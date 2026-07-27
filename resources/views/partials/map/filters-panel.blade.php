<aside id="filters-panel" class="map-side-panel map-glass" x-show="isFiltersOpen" x-transition x-cloak aria-labelledby="filters-title">
    <div class="flex items-center justify-between">
        <h2 id="filters-title" class="text-base font-semibold">Filtres</h2>
        <button type="button" class="map-icon-button" x-on:click="isFiltersOpen = false" aria-label="Fermer les filtres">×</button>
    </div>

    <div class="mt-5 space-y-5">
        <fieldset>
            <legend class="text-sm font-semibold">Couverture</legend>
            <div class="mt-3 space-y-2 text-sm">
                <label class="map-filter-row">
                    <input type="checkbox" x-bind:checked="allStatusesEnabled" x-on:change="toggleAllStatuses($event.target.checked)">
                    <span>Toutes les stations</span>
                </label>
                @foreach ($coverageStatuses as $status)
                    <label class="map-filter-row">
                        <input type="checkbox" value="{{ $status->value }}" x-model="enabledStatuses" x-on:change="refreshVisibility()">
                        <span class="status-node status-{{ $status->value }}" aria-hidden="true"></span>
                        <span>{{ $status->label() }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset>
            <legend class="text-sm font-semibold">Elements a afficher</legend>
            <div class="mt-3 space-y-2 text-sm">
                <label class="map-filter-row"><input type="checkbox" x-model="showStations" x-on:change="refreshLayerVisibility()"> <span>Stations</span></label>
                <label class="map-filter-row"><input type="checkbox" x-model="showLineTracks" x-on:change="refreshLayerVisibility()"> <span>Traces des lignes</span></label>
                <label class="map-filter-row"><input type="checkbox" x-model="showConnections"> <span>Correspondances</span></label>
                <label class="map-filter-row opacity-60"><input type="checkbox" disabled> <span>Entrees et sorties <small class="ml-1 rounded bg-black/5 px-2 py-0.5">Bientot disponible</small></span></label>
            </div>
        </fieldset>

        <button type="button" class="map-secondary-button w-full" x-on:click="resetFilters()">Reinitialiser les filtres</button>
    </div>
</aside>
