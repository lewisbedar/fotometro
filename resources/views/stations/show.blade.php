<x-layouts.app
    :title="'Photos de '.$station->name.' - fotométro'"
    :description="$metaDescription"
    :canonical="route('stations.show', $station)"
    :full-width="true"
>
    <article class="space-y-8" x-data="fotometroLightbox()" x-on:click="handleClick($event)">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Station</p>
                <div class="ratp-sign-frame mt-3">
                    <div class="ratp-sign-plate">
                        <span class="ratp-sign-border" aria-hidden="true"></span>
                        <h1 class="ratp-sign-text">{{ $station->name }}</h1>
                    </div>
                </div>
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

            <div class="flex items-center gap-3 text-sm">
                <span class="h-2.5 w-2.5 flex-none rounded-full" style="background: {{ $station->coverage_status->color() }}"></span>
                <span class="font-semibold text-black">{{ $coverageSummary['overall_percentage'] }} % · {{ $station->coverage_status->description() }}</span>
                @unless ($coverageSummary['essential_coverage']['complete'])
                    <span class="text-black/50">
                        (manque
                        @if ($coverageSummary['essential_coverage']['accesses_missing']->isNotEmpty())
                            {{ $coverageSummary['essential_coverage']['accesses_missing']->count() }} accès
                        @endif
                        @if ($coverageSummary['essential_coverage']['accesses_missing']->isNotEmpty() && ! $coverageSummary['essential_coverage']['platforms_photographed'])
                            ·
                        @endif
                        @unless ($coverageSummary['essential_coverage']['platforms_photographed'])
                            les quais
                        @endunless
                        )
                    </span>
                @endunless
                <div class="relative" x-data="{ open: false }" x-on:mouseenter="open = true" x-on:mouseleave="open = false">
                    <button type="button" class="rounded-md border border-black/10 px-2 py-1 text-xs font-semibold text-black/60 hover:bg-black hover:text-white">Détail</button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 z-10 mt-2 w-80 max-w-[calc(100vw-2rem)] space-y-3 rounded-lg bg-white p-4 shadow-lg ring-1 ring-black/10">
                        @foreach ($coverageSummary['category_breakdown'] as $axis)
                            <div>
                                <div class="flex justify-between gap-4 text-xs">
                                    <span class="font-semibold">{{ $axis['category']->name }}</span>
                                    <span class="text-black/60">{{ $axis['covered'] }} / {{ $axis['total'] }} ({{ $axis['percentage'] }} %)</span>
                                </div>
                                @if ($axis['missing']->isNotEmpty())
                                    <p class="mt-0.5 text-xs text-black/45">Manque : {{ $axis['missing']->pluck('name')->join(', ') }}</p>
                                @endif
                            </div>
                        @endforeach
                        <div>
                            <div class="flex justify-between gap-4 text-xs">
                                <span class="font-semibold">Entrées-sorties</span>
                                <span class="text-black/60">{{ $coverageSummary['access_breakdown']['covered'] }} / {{ $coverageSummary['access_breakdown']['total'] }} ({{ $coverageSummary['access_breakdown']['percentage'] }} %)</span>
                            </div>
                            @if ($coverageSummary['access_breakdown']['missing']->isNotEmpty())
                                <p class="mt-0.5 text-xs text-black/45">Manque :
                                    @foreach ($coverageSummary['access_breakdown']['missing'] as $access){{ $access->displayName($loop->index) }}@if (! $loop->last), @endif @endforeach
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_390px]">
            <div class="space-y-6">
                @if ($featuredPhotos->isEmpty())
                    <div class="rounded-lg bg-white p-8 text-center shadow-sm ring-1 ring-black/5">
                        <h2 class="text-xl font-semibold">Aucune photographie publiée</h2>
                        <p class="mt-2 text-sm text-black/60">Cette station n’a pas encore de photographie prête et publiée.</p>
                    </div>
                @elseif ($featuredPhotos->count() === 1)
                    <x-photo-link :photo="$featuredPhotos->first()" class="block overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5">
                        <img src="{{ $featuredPhotos->first()->web_url }}" alt="{{ $featuredPhotos->first()->publicLabel() }}" class="max-h-[560px] w-full object-cover photo-protected" draggable="false">
                        <span class="block p-4 text-sm text-black/65">{{ $featuredPhotos->first()->publicLabel() }}</span>
                    </x-photo-link>
                @else
                    <div class="grid grid-cols-2 gap-2 overflow-hidden rounded-lg shadow-sm ring-1 ring-black/5">
                        @foreach ($featuredPhotos as $photo)
                            <x-photo-link :photo="$photo" class="group relative block aspect-[4/3] overflow-hidden bg-black/[0.03]">
                                @if ($photo->web_url)
                                    <img src="{{ $photo->web_url }}" alt="{{ $photo->publicLabel() }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105 photo-protected" draggable="false">
                                @endif
                                <span class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-3 text-sm font-medium text-white opacity-100 transition md:opacity-0 md:group-hover:opacity-100">
                                    {{ $photo->publicLabel() }}
                                </span>
                            </x-photo-link>
                        @endforeach
                    </div>
                @endif

                <livewire:station-gallery :station="$station" :key="'station-gallery-'.$station->id" />
            </div>

            <aside class="space-y-6">
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
                    <h2 class="text-xl font-semibold">Station et accès</h2>
                    <p class="mt-2 text-sm text-black/60">{{ $station->accesses->count() }} accès enregistré(s)</p>

                    <div class="mt-4 h-80 overflow-hidden rounded-md bg-[#eef2f0]" x-ref="map" aria-label="Carte de la station et de ses accès"></div>
                    <p class="mt-2 text-xs text-black/55" x-text="mapStatus"></p>

                    <div class="mt-5 space-y-3">
                        @forelse ($accessCards as $card)
                            <article
                                id="access-{{ $card['access']->id }}"
                                class="rounded-md border p-3"
                                x-bind:class="selectedAccessId === '{{ $card['access']->id }}' ? 'border-black bg-black text-white' : 'border-black/10 bg-white'"
                            >
                                <button type="button" class="w-full text-left" x-on:click="selectAccess({{ $card['access']->id }})">
                                    <span class="flex items-center gap-2 font-semibold">
                                        @if ($card['access']->number)
                                            <span class="access-number-badge" aria-hidden="true">{{ $card['access']->number }}</span>
                                        @endif
                                        {{ $card['label'] }}
                                    </span>
                                    @if ($card['access']->street)
                                        <span class="mt-1 block text-sm opacity-70">{{ $card['access']->street }}</span>
                                    @endif
                                    @if ($card['access']->description)
                                        <span class="mt-1 block text-sm opacity-70">{{ $card['access']->description }}</span>
                                    @endif
                                    <span class="mt-2 block text-xs font-semibold">{{ $card['photo_count'] }} photographie(s)</span>
                                </button>

                                @if ($card['preview_photos']->isNotEmpty())
                                    <div class="mt-3 flex gap-2">
                                        @foreach ($card['preview_photos'] as $preview)
                                            <x-photo-link :photo="$preview" class="block h-14 w-16 overflow-hidden rounded bg-black/10">
                                                @if ($preview->thumbnail_url)
                                                    <img src="{{ $preview->thumbnail_url }}" alt="{{ $preview->publicLabel() }}" class="h-full w-full object-cover photo-protected" draggable="false">
                                                @endif
                                            </x-photo-link>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="rounded-md bg-black/5 p-3 text-sm text-black/65">Aucun accès enregistré pour cette station.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </section>

        <x-photo-lightbox />
    </article>
</x-layouts.app>
