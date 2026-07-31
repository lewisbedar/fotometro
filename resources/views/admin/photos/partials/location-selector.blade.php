@php
    $initialLineId = old('line_id', $selectedLineId ?? null);
    $initialStationId = old('station_id', $photo->station_id ?? null);
    $initialAccessId = old('station_access_id', $photo->station_access_id ?? null);

    // A photo with no line_id on a multi-line station is a deliberate
    // "covers the whole station" choice (see the checkbox below), not an
    // oversight — the edit form has to reflect that on load, otherwise
    // just saving the form again (without touching anything) would silently
    // reassign it to whichever line formData() guessed for the Station
    // dropdown, undoing the photographer's original intent.
    $hasOldLocationInput = old('line_id') !== null || old('station_id') !== null;
    $initialCoversWholeStation = $hasOldLocationInput
        ? old('line_id') === ''
        : ($photo->exists
            && $photo->line_id === null
            && $photo->station
            && $photo->station->lines->where('is_active', true)->count() > 1);
@endphp

<section
    class="space-y-4 rounded-lg border border-black/10 bg-black/[0.02] p-4"
    x-data="fotometroPhotoForm({{
        \Illuminate\Support\Js::from([
            'initialLineId' => $initialLineId,
            'initialStationId' => $initialStationId,
            'initialAccessId' => $initialAccessId,
            'initialCoversWholeStation' => $initialCoversWholeStation,
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

    <input type="hidden" name="line_id" :value="coversWholeStation ? '' : lineId">

    <div x-show="stationHasMultipleLines()" x-cloak class="flex items-start gap-2 rounded-md border border-black/10 bg-white p-3">
        <label class="flex flex-1 items-start gap-2 text-sm font-semibold">
            <input type="checkbox" class="mt-0.5" x-model="coversWholeStation">
            <span>Cette photo concerne toute la station, pas seulement la ligne choisie</span>
        </label>
        <div class="group relative">
            <button type="button" class="text-black/50 hover:text-black" aria-label="Aide">
                <x-icons.help class="h-5 w-5" />
            </button>
            <div
                class="pointer-events-none absolute right-0 z-10 mt-2 w-72 rounded-md border border-black/10 bg-white p-3 text-sm text-black/70 opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-within:opacity-100"
            >
                Certaines stations desservent plusieurs lignes (correspondances). Cochez cette case si la photo montre un élément commun à toute la station — une entrée, un couloir de correspondance, un totem — plutôt qu’un élément propre à une seule ligne.
                <p class="mt-2 text-black/55">Exemple : l’entrée principale de Champs-Élysées - Clemenceau (lignes 1 et 13) concerne toute la station.</p>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-black/10 bg-white p-3">
        <div class="mb-2 flex items-center justify-between gap-3">
            <p class="text-sm font-semibold">Repère cartographique</p>
            <p class="text-xs text-black/55" x-text="mapStatus"></p>
        </div>
        <div x-ref="map" class="h-64 min-h-64 overflow-hidden rounded-md bg-[#eef2f0]" aria-label="Carte de la station et de ses accès"></div>
    </div>
</section>
