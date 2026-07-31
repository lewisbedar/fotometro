<aside class="map-progress-panel map-glass hidden md:block" :class="{ 'is-collapsed': progressCollapsed }" aria-labelledby="global-progress-title">
    <button
        type="button"
        class="flex w-full items-center gap-2 text-left"
        :class="progressCollapsed ? 'h-11 justify-center' : ''"
        x-on:click="progressCollapsed = ! progressCollapsed"
        aria-controls="global-progress-body"
        aria-label="Afficher ou masquer la progression globale"
        x-bind:aria-expanded="(! progressCollapsed).toString()"
    >
        <x-icons.data-usage class="h-5 w-5 shrink-0 text-black/60" />
        <h2 id="global-progress-title" class="flex-1 text-sm font-semibold" x-show="! progressCollapsed">Progression globale</h2>
        <x-icons.chevron-up class="h-4 w-4 shrink-0 text-black/45" x-show="! progressCollapsed" aria-hidden="true" />
    </button>
    <div id="global-progress-body" class="mt-4 grid grid-cols-[72px_1fr] gap-4" x-show="! progressCollapsed" x-cloak>
        <div class="progress-ring" style="--progress: {{ $progressPercentage }}">
            <strong>{{ $progressPercentage }}</strong>
            <span>/100</span>
        </div>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between gap-4"><dt>Stations complètes</dt><dd class="font-semibold">{{ $coverageStatusCounts['complete'] ?? 0 }}</dd></div>
            <div class="flex justify-between gap-4"><dt>Documentées</dt><dd class="font-semibold">{{ $coverageStatusCounts['documented'] ?? 0 }}</dd></div>
            <div class="flex justify-between gap-4"><dt>En cours</dt><dd class="font-semibold">{{ $coverageStatusCounts['in_progress'] ?? 0 }}</dd></div>
            <div class="flex justify-between gap-4"><dt>Planifiées</dt><dd class="font-semibold">{{ $coverageStatusCounts['planned'] ?? 0 }}</dd></div>
            <div class="flex justify-between gap-4"><dt>Non commencées</dt><dd class="font-semibold">{{ $coverageStatusCounts['not_started'] ?? 0 }}</dd></div>
        </dl>
        <p class="col-span-2 text-xs text-black/60">{{ $documentedStationCount }} stations documentées sur {{ $stationCount }}</p>
    </div>
</aside>
