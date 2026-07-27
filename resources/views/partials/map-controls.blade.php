<div class="space-y-6">
    <div>
        <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-black/55">Lignes</h2>
        <div class="mt-3 grid grid-cols-2 gap-2">
            @foreach ($lines as $line)
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-md border border-black/10 bg-white p-2 text-left text-sm hover:border-black focus:outline-none focus:ring-2 focus:ring-black/20"
                    x-on:click="selectLine({{ $line->id }})"
                    x-bind:class="{ 'ring-2 ring-black/30': selectedLineId === {{ $line->id }} }"
                >
                    <span class="grid h-8 min-w-8 place-items-center rounded-full px-1 text-xs font-bold" style="background: {{ $line->color }}; color: {{ $line->text_color }}">{{ $line->code }}</span>
                    <span>
                        <span class="block font-medium">{{ $line->name }}</span>
                        <span class="block text-xs text-black/55">{{ $line->stations_count }} station(s)</span>
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    <div>
        <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-black/55">Couverture</h2>
        <div class="mt-3 space-y-2">
            @foreach ($coverageStatuses as $status)
                <label class="flex items-center gap-3 text-sm">
                    <input type="checkbox" class="rounded border-black/20" value="{{ $status->value }}" x-model="enabledStatuses" x-on:change="refreshVisibility()">
                    <span class="h-3 w-3 rounded-full" style="background: {{ $status->color() }}"></span>
                    <span>{{ $status->description() }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="rounded-lg bg-[#f6f1e8] p-4">
        <p class="text-sm text-black/60">{{ $documentedStationCount }} stations documentées sur {{ $stationCount }}</p>
        <div class="mt-3 h-2 rounded-full bg-black/10">
            <div class="h-2 rounded-full bg-[#151515]" style="width: {{ $progressPercentage }}%"></div>
        </div>
        <p class="mt-2 text-sm font-semibold">Progression globale : {{ $progressPercentage }} %</p>
        <p class="mt-3 text-xs leading-5 text-black/55">{{ $lineCount }} ligne(s), {{ $stationsWithoutCoordinates }} station(s) sans coordonnées.</p>
    </div>

    <button type="button" class="w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm font-semibold hover:bg-black hover:text-white" x-on:click="resetFilters()">
        Réinitialiser la sélection
    </button>
</div>
