<x-layouts.app
    :title="'Photos de '.$station->name.' - fotometro'"
    :description="$metaDescription"
    :canonical="route('stations.show', $station)"
    :full-width="true"
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
                            {{ $line->code }}
                        </a>
                    @endforeach
                </div>
                <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm text-black/65">
                    <span class="font-semibold text-black">{{ $coverageSummary['total_photos'] }} photographie(s)</span>
                    @if ($coverageSummary['last_photo_at'])
                        <span>Dernière photographie : {{ $coverageSummary['last_photo_at']->format('d/m/Y') }}</span>
                    @endif
                </div>
            </div>

            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">Documentation photographique</p>
                <p class="mt-2 text-xl font-semibold">{{ $station->coverage_status->description() }}</p>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt>Photos</dt><dd class="font-semibold">{{ $coverageSummary['total_photos'] }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Catégories représentées</dt><dd class="font-semibold">{{ $coverageSummary['represented_categories'] }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Accès photographiés</dt><dd class="font-semibold">{{ $coverageSummary['photographed_accesses'] }} / {{ $coverageSummary['total_accesses'] }}</dd></div>
                </dl>
            </div>
        </header>

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_390px]">
            <div class="space-y-6">
                @if ($featuredPhoto?->web_url)
                    <figure class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5">
                        <img src="{{ $featuredPhoto->web_url }}" alt="{{ $featuredPhoto->title ?: 'Photographie de '.$station->name }}" class="max-h-[560px] w-full object-cover">
                        <figcaption class="p-4 text-sm text-black/65">
                            {{ $featuredPhoto->title ?: $featuredPhoto->category?->name ?: 'Photographie principale' }}
                        </figcaption>
                    </figure>
                @else
                    <div class="rounded-lg bg-white p-8 text-center shadow-sm ring-1 ring-black/5">
                        <h2 class="text-xl font-semibold">Aucune photographie publiée</h2>
                        <p class="mt-2 text-sm text-black/60">Cette station n’a pas encore de photographie prête et publiée.</p>
                    </div>
                @endif

                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-semibold">Galerie</h2>
                            <p class="mt-1 text-sm text-black/60">{{ $photos->total() }} photographie(s) dans cette sélection</p>
                        </div>
                        @if ($selectedCategory || $selectedAccess)
                            <a href="{{ route('stations.show', $station) }}" class="rounded-md border border-black/10 px-3 py-2 text-sm font-semibold hover:bg-black hover:text-white">Réinitialiser</a>
                        @endif
                    </div>

                    <nav class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="Filtres de galerie">
                        <a href="{{ route('stations.show', ['station' => $station, ...request()->except(['category', 'page'])]) }}" @class(['rounded-full px-3 py-1 font-semibold ring-1 ring-black/10', 'bg-black text-white' => ! $selectedCategory, 'bg-black/5' => $selectedCategory])>
                            Toutes ({{ $coverageSummary['total_photos'] }})
                        </a>
                        @foreach ($categoryFilters as $item)
                            <a href="{{ route('stations.show', ['station' => $station, ...request()->except(['category', 'page']), 'category' => $item['category']->slug]) }}" @class(['rounded-full px-3 py-1 font-semibold ring-1 ring-black/10', 'bg-black text-white' => $selectedCategory?->id === $item['category']->id, 'bg-black/5' => $selectedCategory?->id !== $item['category']->id])>
                                {{ $item['category']->name }} ({{ $item['count'] }})
                            </a>
                        @endforeach
                    </nav>

                    @if ($subCategoryFilters->isNotEmpty())
                        <div class="mt-4 border-t border-black/10 pt-4">
                            <p class="text-sm font-semibold">{{ $selectedCategory->name }}</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-sm">
                                @foreach ($subCategoryFilters as $item)
                                    <a href="{{ route('stations.show', ['station' => $station, ...request()->except(['category', 'page']), 'category' => $item['category']->slug]) }}" class="rounded-full bg-white px-3 py-1 font-semibold ring-1 ring-black/10 hover:bg-black hover:text-white">
                                        {{ $item['category']->name }} ({{ $item['count'] }})
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @forelse ($photos as $photo)
                            <a href="{{ route('photos.show', $photo) }}" class="group overflow-hidden rounded-md bg-black/[0.03] ring-1 ring-black/10 transition hover:-translate-y-0.5 hover:shadow-md">
                                @if ($photo->thumbnail_url)
                                    <img src="{{ $photo->thumbnail_url }}" alt="{{ $photo->title ?: 'Photographie de '.$station->name }}" class="aspect-[4/3] w-full object-cover transition group-hover:scale-105">
                                @else
                                    <span class="grid aspect-[4/3] place-items-center bg-black/5 text-sm text-black/55">Aperçu indisponible</span>
                                @endif
                                <span class="block p-3">
                                    <span class="block truncate text-sm font-semibold">{{ $photo->title ?: $photo->original_filename }}</span>
                                    <span class="mt-1 block text-xs text-black/55">{{ $photo->category?->name ?: 'Sans catégorie' }}</span>
                                </span>
                            </a>
                        @empty
                            <p class="col-span-full rounded-md bg-black/5 p-4 text-sm text-black/65">Aucune photographie ne correspond à ces filtres.</p>
                        @endforelse
                    </div>

                    <div class="mt-6">
                        {{ $photos->links() }}
                    </div>
                </section>
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

                <section
                    class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5"
                    x-data="fotometroStationAccessMap({{ \Illuminate\Support\Js::from(['mapConfig' => [
                        'basemapDriver' => $mapConfig['basemap_driver'],
                        'styleUrl' => $mapConfig['style_url'],
                        'rasterUrl' => $mapConfig['raster_url'],
                        'rasterTileSize' => $mapConfig['raster_tile_size'],
                        'attribution' => $mapConfig['attribution'],
                        'centerLongitude' => $mapConfig['center']['longitude'],
                        'centerLatitude' => $mapConfig['center']['latitude'],
                        'zoom' => $mapConfig['center']['zoom'],
                        'maxZoom' => $mapConfig['center']['max_zoom'],
                    ], 'payload' => $accessMapPayload, 'selectedAccessId' => $selectedAccess?->id]) }})"
                    x-init="init()"
                >
                    <h2 class="text-xl font-semibold">Entrées et sorties</h2>
                    <p class="mt-2 text-sm text-black/60">{{ $station->accesses->count() }} accès enregistré(s)</p>

                    <div class="mt-4 h-72 overflow-hidden rounded-md bg-[#eef2f0]" x-ref="map" aria-label="Carte des accès de la station"></div>
                    <p class="mt-2 text-xs text-black/55" x-text="mapStatus"></p>

                    <div class="mt-5 space-y-3">
                        @forelse ($accessCards as $card)
                            <article id="access-{{ $card['access']->id }}" @class(['rounded-md border p-3', 'border-black bg-black text-white' => $selectedAccess?->id === $card['access']->id, 'border-black/10 bg-white' => $selectedAccess?->id !== $card['access']->id])>
                                <button type="button" class="w-full text-left" x-on:click="selectAccess({{ $card['access']->id }})">
                                    <span class="block font-semibold">{{ $card['label'] }}</span>
                                    @if ($card['access']->access_type || $card['access']->street)
                                        <span class="mt-1 block text-sm opacity-70">{{ collect([$card['access']->access_type, $card['access']->street])->filter()->implode(' - ') }}</span>
                                    @endif
                                    @if ($card['access']->description)
                                        <span class="mt-1 block text-sm opacity-70">{{ $card['access']->description }}</span>
                                    @endif
                                    <span class="mt-2 block text-xs font-semibold">{{ $card['photo_count'] }} photographie(s)</span>
                                </button>

                                @if ($card['preview_photos']->isNotEmpty())
                                    <div class="mt-3 flex gap-2">
                                        @foreach ($card['preview_photos'] as $preview)
                                            <a href="{{ route('photos.show', $preview) }}" class="block h-14 w-16 overflow-hidden rounded bg-black/10">
                                                @if ($preview->thumbnail_url)
                                                    <img src="{{ $preview->thumbnail_url }}" alt="{{ $preview->title ?: 'Photographie de '.$card['label'] }}" class="h-full w-full object-cover">
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                    <a href="{{ route('stations.show', ['station' => $station, ...request()->except(['access', 'page']), 'access' => $card['access']->id]) }}" class="mt-3 inline-flex text-sm font-semibold underline">Filtrer la galerie</a>
                                @endif
                            </article>
                        @empty
                            <p class="rounded-md bg-black/5 p-3 text-sm text-black/65">Aucun accès enregistré pour cette station.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </section>
    </article>
</x-layouts.app>
