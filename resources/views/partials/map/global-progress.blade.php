<aside class="map-progress-panel map-glass" aria-labelledby="global-progress-title">
    <button type="button" class="flex w-full items-center justify-between gap-3 text-left md:pointer-events-none" x-on:click="progressCollapsed = ! progressCollapsed" x-bind:aria-expanded="(! progressCollapsed).toString()">
        <h2 id="global-progress-title" class="text-sm font-semibold">Progression globale</h2>
        <span class="md:hidden" x-text="progressCollapsed ? '+' : '-'"></span>
    </button>
    <div class="mt-4 grid grid-cols-[72px_1fr] gap-4" x-show="! progressCollapsed || ! isSmallScreen" x-cloak>
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
