@php
    $initialLineId = old('line_id', $selectedLineId ?? null);
    $initialStationId = old('station_id', $photo->station_id ?? null);
    $initialAccessId = old('station_access_id', $photo->station_access_id ?? null);
@endphp

<section
    class="space-y-4 rounded-lg border border-black/10 bg-black/[0.02] p-4"
    x-data="fotometroPhotoForm({{
        \Illuminate\Support\Js::from([
            'initialLineId' => $initialLineId,
            'initialStationId' => $initialStationId,
            'initialAccessId' => $initialAccessId,
            'lineStationsUrl' => route('admin.api.lines.stations', ['line' => '__LINE__']),
            'stationAccessesUrl' => route('admin.api.stations.accesses', ['station' => '__STATION__']),
            'mapConfig' => $mapConfig,
        ])
    }})"
    x-init="init()"
>
    <div>
        <h2 class="text-base font-semibold">Localisation</h2>
        <p class="mt-1 text-sm text-black/60">Choisissez d’abord une ligne, puis une station et éventuellement un accès associé.</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <label class="block text-sm font-semibold" for="photo-line-id">
            Ligne
            <select
                id="photo-line-id"
                name="line_id"
                class="mt-1 w-full rounded-md border border-black/15 bg-white p-2"
                x-model="lineId"
                x-on:change="lineChanged()"
            >
                <option value="">Sélectionner une ligne</option>
                @foreach($lines as $line)
                    <option value="{{ $line->id }}" style="background-color: {{ $line->color }}22;">
                        Ligne {{ $line->code }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm font-semibold" for="photo-station-id">
            Station
            <select
                id="photo-station-id"
                name="station_id"
                class="mt-1 w-full rounded-md border border-black/15 bg-white p-2 disabled:bg-black/5 disabled:text-black/45"
                required
                x-model="stationId"
                x-on:change="stationChanged()"
                x-bind:disabled="! lineId || loadingStations"
            >
                <option value="" x-text="loadingStations ? 'Chargement des stations...' : 'Sélectionner une station'"></option>
                <template x-for="station in stations" :key="station.id">
                    <option :value="station.id" x-text="station.name"></option>
                </template>
            </select>
        </label>

        <label class="block text-sm font-semibold" for="photo-station-access-id">
            Accès optionnel
            <select
                id="photo-station-access-id"
                name="station_access_id"
                class="mt-1 w-full rounded-md border border-black/15 bg-white p-2 disabled:bg-black/5 disabled:text-black/45"
                x-model="accessId"
                x-on:change="accessChanged()"
                x-bind:disabled="! stationId || loadingAccesses"
            >
                <option value="" x-text="loadingAccesses ? 'Chargement des accès...' : 'Aucun accès'"></option>
                <template x-for="access in accesses" :key="access.id">
                    <option :value="access.id" x-text="access.name"></option>
                </template>
            </select>
        </label>
    </div>

    <div class="rounded-lg border border-black/10 bg-white p-3">
        <div class="mb-2 flex items-center justify-between gap-3">
            <p class="text-sm font-semibold">Repère cartographique</p>
            <p class="text-xs text-black/55" x-text="mapStatus"></p>
        </div>
        <div x-ref="map" class="h-64 min-h-64 overflow-hidden rounded-md bg-[#eef2f0]" aria-label="Carte de la station et de ses accès"></div>
    </div>
</section>
