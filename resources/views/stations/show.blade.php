<x-layouts.app
    :title="$station->name.' - fotometro'"
    :description="$metaDescription"
    :canonical="route('stations.show', $station)"
>
    <article class="space-y-8">
        <a href="{{ route('home') }}" class="text-sm font-medium text-black/65 hover:text-black hover:underline">Retour à la carte</a>

        <header class="grid gap-6 lg:grid-cols-[1fr_360px] lg:items-start">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Station</p>
                <h1 class="mt-2 text-4xl font-semibold">{{ $station->name }}</h1>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($station->lines as $line)
                        <a href="{{ route('lines.show', $line) }}" class="rounded-full px-3 py-1 text-sm font-bold" style="background: {{ $line->color }}; color: {{ $line->text_color }}">
                            Ligne {{ $line->code }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">État photographique</p>
                <p class="mt-2 text-xl font-semibold">{{ $station->coverage_status->description() }}</p>
            </div>
        </header>

        <section class="grid gap-6 lg:grid-cols-[1fr_380px]">
            <div class="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
                <div>
                    <h2 class="text-xl font-semibold">Informations</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-sm text-black/55">Commune</dt><dd class="font-medium">{{ $station->city ?? 'À compléter' }}</dd></div>
                        <div><dt class="text-sm text-black/55">Code postal</dt><dd class="font-medium">{{ $station->postal_code ?? 'À compléter' }}</dd></div>
                        <div><dt class="text-sm text-black/55">Arrondissement</dt><dd class="font-medium">{{ $station->district ?? 'À compléter' }}</dd></div>
                        <div><dt class="text-sm text-black/55">Ouverture</dt><dd class="font-medium">{{ $station->opening_date?->format('d/m/Y') ?? 'À compléter' }}</dd></div>
                        <div><dt class="text-sm text-black/55">Latitude</dt><dd class="font-medium">{{ $station->latitude ?? 'À compléter' }}</dd></div>
                        <div><dt class="text-sm text-black/55">Longitude</dt><dd class="font-medium">{{ $station->longitude ?? 'À compléter' }}</dd></div>
                    </dl>
                </div>

                <div>
                    <h2 class="text-xl font-semibold">Description</h2>
                    <p class="mt-3 leading-7 text-black/70">{{ $station->description ?? 'Description à compléter.' }}</p>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5">
                    <div
                        class="fotometro-static-map h-72"
                        data-basemap-driver="{{ $mapConfig['basemap_driver'] }}"
                        data-map-style="{{ $mapConfig['style_url'] }}"
                        data-raster-url="{{ $mapConfig['raster_url'] }}"
                        data-raster-tile-size="{{ $mapConfig['raster_tile_size'] }}"
                        data-map-attribution="{{ $mapConfig['attribution'] }}"
                        data-latitude="{{ $station->latitude }}"
                        data-longitude="{{ $station->longitude }}"
                        data-map-max-zoom="{{ $mapConfig['center']['max_zoom'] }}"
                        data-label="{{ $station->name }}"
                        data-status-color="{{ $station->coverage_status->color() }}"
                    ></div>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                    <h2 class="text-xl font-semibold">Galerie</h2>
                    <p class="mt-3 text-black/65">Aucune photographie publiée pour cette station.</p>
                </div>
            </aside>
        </section>
    </article>
</x-layouts.app>
