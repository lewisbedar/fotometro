<x-layouts.app title="Administration - fotometro">
    <div class="space-y-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p>
            <h1 class="mt-2 text-3xl font-semibold">Tableau de bord</h1>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">Lignes</p>
                <p class="mt-2 text-4xl font-semibold">{{ $lineCount }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">Stations</p>
                <p class="mt-2 text-4xl font-semibold">{{ $stationCount }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">Stations photographiées</p>
                <p class="mt-2 text-4xl font-semibold">{{ $documentedStationCount }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">Non documentées</p>
                <p class="mt-2 text-4xl font-semibold">{{ $undocumentedStationCount }}</p>
            </div>
        </div>
    </div>
</x-layouts.app>
