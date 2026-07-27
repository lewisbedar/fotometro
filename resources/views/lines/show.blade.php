<x-layouts.app
    :title="$line->name.' - fotometro'"
    :description="$metaDescription"
    :canonical="route('lines.show', $line)"
>
    <article class="space-y-8">
        <a href="{{ route('home') }}" class="text-sm font-medium text-black/65 hover:text-black hover:underline">Retour a la carte</a>

        <header class="grid gap-6 lg:grid-cols-[1fr_360px] lg:items-start">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Ligne</p>
                <div class="mt-3 flex items-center gap-4">
                    <span class="grid h-16 min-w-16 place-items-center rounded-full px-2 text-xl font-bold" style="background: {{ $line->color }}; color: {{ $line->text_color }}">{{ $line->code }}</span>
                    <h1 class="text-4xl font-semibold">{{ $line->name }}</h1>
                </div>
            </div>

            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">{{ $documentedCount }} stations documentees sur {{ $stationCount }}</p>
                <div class="mt-3 h-2 rounded-full bg-black/10">
                    <div class="h-2 rounded-full" style="width: {{ $coveragePercentage }}%; background: {{ $line->color }}"></div>
                </div>
                <p class="mt-2 text-xl font-semibold">{{ $coveragePercentage }} % de couverture</p>
            </div>
        </header>

        <section class="grid gap-6 lg:grid-cols-[1fr_380px]">
            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="text-xl font-semibold">Stations</h2>
                <ol class="mt-5 space-y-3">
                    @foreach ($line->stations as $station)
                        <li class="flex items-center justify-between gap-4 rounded-md border border-black/10 p-3">
                            <div>
                                <a href="{{ route('stations.show', $station) }}" class="font-semibold hover:underline">{{ $station->name }}</a>
                                <p class="mt-1 text-sm text-black/60">{{ $station->coverage_status->description() }}</p>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach ($station->lines->reject(fn ($connection) => $connection->id === $line->id) as $connection)
                                        <span class="rounded-full px-2 py-0.5 text-xs font-bold" style="background: {{ $connection->color }}; color: {{ $connection->text_color }}">{{ $connection->code }}</span>
                                    @endforeach
                                </div>
                            </div>
                            <span class="text-sm text-black/45">#{{ $station->pivot->position }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <aside class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5">
                <div
                    class="fotometro-static-map h-80"
                    data-basemap-driver="{{ $mapConfig['basemap_driver'] }}"
                    data-map-style="{{ $mapConfig['style_url'] }}"
                    data-raster-url="{{ $mapConfig['raster_url'] }}"
                    data-raster-tile-size="{{ $mapConfig['raster_tile_size'] }}"
                    data-map-attribution="{{ $mapConfig['attribution'] }}"
                    data-line='@json($line->path_geojson)'
                    data-line-stations='@json($lineStationCoordinates)'
                    data-line-color="{{ $line->color }}"
                    data-map-max-zoom="{{ $mapConfig['center']['max_zoom'] }}"
                    data-label="{{ $line->name }}"
                ></div>
            </aside>
        </section>
    </article>
</x-layouts.app>
