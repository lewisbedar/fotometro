<div
    class="flex h-[calc(100dvh-12rem)] min-h-[420px] flex-col gap-3"
    x-data
    x-on:keydown.window.left="if (!['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) $wire.startReject()"
    x-on:keydown.window.right="if (!['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) $wire.approve()"
    x-on:keydown.window.up="if (!['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) $wire.startEdit()"
    x-on:keydown.window.down="if (!['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) $wire.startEdit()"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p>
            <h1 class="mt-1 text-2xl font-semibold">Modération</h1>
        </div>
        <p class="text-sm text-black/60">{{ $this->pendingCount }} photo(s) en attente</p>
    </div>

    @error('publish') <p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p> @enderror

    @if (! $this->currentPhoto)
        <div class="flex flex-1 flex-col items-center justify-center rounded-lg bg-white text-center shadow-sm ring-1 ring-black/5">
            <p class="text-lg font-semibold">Aucune photo en attente</p>
            <p class="mt-2 text-sm text-black/60">La file de modération est vide pour le moment.</p>
        </div>
    @else
        <div
            class="flex min-h-0 flex-none flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-4 py-2 text-sm shadow-sm ring-1 ring-black/5"
            x-data="{ mapModal: { open: false, lat: null, lng: null, label: '' } }"
        >
            <div class="flex min-w-0 flex-wrap items-center gap-2">
                @if ($this->currentPhoto->station->latitude && $this->currentPhoto->station->longitude)
                    <button
                        type="button"
                        class="ratp-sign-mini cursor-pointer"
                        title="Voir la station sur la carte"
                        x-on:click="mapModal = { open: true, lat: {{ $this->currentPhoto->station->latitude }}, lng: {{ $this->currentPhoto->station->longitude }}, label: {{ \Illuminate\Support\Js::from($this->currentPhoto->station->name) }} }"
                    >
                        <span class="ratp-sign-mini-plate"><span class="ratp-sign-mini-text">{{ $this->currentPhoto->station->name }}</span></span>
                    </button>
                @else
                    <span class="ratp-sign-mini"><span class="ratp-sign-mini-plate"><span class="ratp-sign-mini-text">{{ $this->currentPhoto->station->name }}</span></span></span>
                @endif

                @foreach ($this->currentPhoto->station->lines as $line)
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" style="background: {{ $line->color }}; color: {{ $line->text_color }}">{{ $line->code }}</span>
                @endforeach

                @if ($access = $this->currentPhoto->stationAccess)
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-full bg-black/5 px-2.5 py-1 text-xs font-semibold hover:bg-black/10"
                        title="Voir l'accès sur la carte"
                        @if ($access->latitude && $access->longitude)
                            x-on:click="mapModal = { open: true, lat: {{ $access->latitude }}, lng: {{ $access->longitude }}, label: {{ \Illuminate\Support\Js::from($access->displayName()) }} }"
                        @endif
                    >
                        @if ($access->number)<span class="access-number-badge">{{ $access->number }}</span>@endif
                        {{ $access->displayName() }}
                    </button>
                @else
                    <span class="text-xs text-black/50">Aucun accès</span>
                @endif

                <span class="text-xs text-black/60">{{ $this->currentPhoto->categories->isNotEmpty() ? $this->currentPhoto->categories->pluck('name')->join(', ') : 'Sans catégorie' }}</span>
                @if ($this->currentPhoto->description)
                    <span class="text-xs text-black/70">— {{ $this->currentPhoto->description }}</span>
                @endif
            </div>
            <span class="flex-none text-xs text-black/45">Soumise par {{ $this->currentPhoto->user?->name ?? 'admin' }} le {{ $this->currentPhoto->created_at->format('d/m/Y') }}</span>

            <template x-if="mapModal.open">
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-on:click.self="mapModal.open = false">
                    <div class="w-full max-w-xl rounded-lg bg-white p-4 shadow-xl">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="font-semibold" x-text="mapModal.label"></p>
                            <button type="button" x-on:click="mapModal.open = false" class="text-black/50 hover:text-black"><x-icons.close class="h-5 w-5" /></button>
                        </div>
                        <div
                            class="fotometro-static-map h-96 overflow-hidden rounded-md bg-[#eef2f0]"
                            data-interactive="true"
                            x-bind:data-latitude="mapModal.lat"
                            x-bind:data-longitude="mapModal.lng"
                            x-bind:data-label="mapModal.label"
                            data-status-color="#151515"
                            data-basemap-driver="{{ $mapConfig['basemapDriver'] }}"
                            data-raster-url="{{ $mapConfig['rasterUrl'] }}"
                            data-raster-tile-size="{{ $mapConfig['rasterTileSize'] }}"
                            data-map-attribution="{{ $mapConfig['attribution'] }}"
                            data-map-center-longitude="{{ $mapConfig['centerLongitude'] }}"
                            data-map-center-latitude="{{ $mapConfig['centerLatitude'] }}"
                            data-map-zoom="{{ $mapConfig['zoom'] }}"
                            data-map-max-zoom="{{ $mapConfig['maxZoom'] }}"
                            x-init="$nextTick(() => window.fotometroRefreshStaticMaps())"
                        ></div>
                    </div>
                </div>
            </template>
        </div>

        <div
            class="relative min-h-0 flex-1 overflow-hidden rounded-lg bg-black shadow-sm ring-1 ring-black/5"
            wire:key="photo-{{ $this->currentPhoto->id }}"
            x-data="{ lightbox: false }"
        >
            @if ($this->currentPhoto->web_url)
                <img src="{{ $this->currentPhoto->web_url }}" alt="" class="h-full w-full cursor-zoom-in object-contain" x-on:click="lightbox = true">
            @else
                <div class="flex h-full items-center justify-center text-sm text-white/50">Pas d’aperçu</div>
            @endif

            {{-- Halos: a soft, always-present tint on each edge signals the action is available; it strengthens on hover instead of a plain floating button. --}}
            <button
                type="button"
                wire:click="startReject"
                title="Refuser"
                class="group absolute inset-y-0 left-0 flex w-1/4 items-center justify-start bg-gradient-to-r from-red-600/30 via-red-600/5 to-transparent pl-4 transition hover:from-red-600/50 hover:via-red-600/15 sm:pl-8"
            >
                <span class="rounded-full bg-white/95 p-3 text-red-700 shadow-lg transition group-hover:scale-110">
                    <x-icons.close class="h-6 w-6" />
                </span>
            </button>

            <button
                type="button"
                wire:click="approve"
                title="Approuver"
                class="group absolute inset-y-0 right-0 flex w-1/4 items-center justify-end bg-gradient-to-l from-green-600/30 via-green-600/5 to-transparent pr-4 transition hover:from-green-600/50 hover:via-green-600/15 sm:pr-8"
            >
                <span class="rounded-full bg-white/95 p-3 text-green-700 shadow-lg transition group-hover:scale-110">
                    <x-icons.check class="h-6 w-6" />
                </span>
            </button>

            <button
                type="button"
                wire:click="startEdit"
                title="Modifier (station, accès, catégories, description)"
                class="group absolute inset-x-0 top-0 flex h-1/5 items-start justify-center bg-gradient-to-b from-black/35 via-black/5 to-transparent pt-3 opacity-0 transition hover:opacity-100"
            >
                <span class="rounded-full bg-white/95 p-2 text-black shadow-lg transition group-hover:scale-110">
                    <x-icons.edit class="h-5 w-5" />
                </span>
            </button>

            <button
                type="button"
                wire:click="startEdit"
                title="Modifier (station, accès, catégories, description)"
                class="group absolute inset-x-0 bottom-0 flex h-1/5 items-end justify-center bg-gradient-to-t from-black/35 via-black/5 to-transparent pb-3 opacity-0 transition hover:opacity-100"
            >
                <span class="rounded-full bg-white/95 p-2 text-black shadow-lg transition group-hover:scale-110">
                    <x-icons.edit class="h-5 w-5" />
                </span>
            </button>

            <template x-if="lightbox">
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4" x-on:click.self="lightbox = false">
                    <button type="button" x-on:click="lightbox = false" class="absolute right-4 top-4 text-white/80 hover:text-white"><x-icons.close class="h-7 w-7" /></button>
                    <div class="flex max-h-full max-w-full flex-col gap-4 overflow-y-auto lg:flex-row lg:items-start" x-on:click.stop>
                        <div
                            class="max-h-[85vh] max-w-full select-none overflow-hidden rounded-lg lg:max-w-[70vw]"
                            x-data="{
                                scale: 1, tx: 0, ty: 0, dragging: false, startX: 0, startY: 0,
                                clampPan() {
                                    const rect = $el.getBoundingClientRect();
                                    const maxX = Math.max(0, (this.scale - 1) * rect.width / 2);
                                    const maxY = Math.max(0, (this.scale - 1) * rect.height / 2);
                                    this.tx = Math.min(maxX, Math.max(-maxX, this.tx));
                                    this.ty = Math.min(maxY, Math.max(-maxY, this.ty));
                                },
                            }"
                            x-on:wheel.prevent="
                                const rect = $el.getBoundingClientRect();
                                const cx = $event.clientX - rect.left - rect.width / 2;
                                const cy = $event.clientY - rect.top - rect.height / 2;
                                const next = Math.min(4, Math.max(1, scale + ($event.deltaY > 0 ? -0.25 : 0.25)));
                                tx = cx - (cx - tx) * (next / scale);
                                ty = cy - (cy - ty) * (next / scale);
                                scale = next;
                                if (scale === 1) { tx = 0; ty = 0; }
                                clampPan();
                            "
                            x-on:mousedown="if (scale > 1) { dragging = true; startX = $event.clientX - tx; startY = $event.clientY - ty; }"
                            x-on:mousemove.window="if (dragging) { tx = $event.clientX - startX; ty = $event.clientY - startY; clampPan(); }"
                            x-on:mouseup.window="dragging = false"
                        >
                            <img
                                src="{{ $this->currentPhoto->web_url }}"
                                alt=""
                                draggable="false"
                                title="Molette pour zoomer, glisser pour déplacer"
                                x-on:dragstart.prevent
                                x-bind:style="`transform: translate(${tx}px, ${ty}px) scale(${scale}); cursor: ${scale > 1 ? (dragging ? 'grabbing' : 'grab') : 'zoom-in'}`"
                                class="max-h-[85vh] max-w-full rounded-lg object-contain"
                            >
                        </div>
                        <div class="w-full flex-none space-y-2 rounded-lg bg-white p-4 text-sm lg:w-72">
                            <p class="font-semibold">Données EXIF</p>
                            @if ($this->currentPhoto->taken_at || $this->currentPhoto->camera_make || $this->currentPhoto->camera_model || $this->currentPhoto->lens || $this->currentPhoto->focal_length || $this->currentPhoto->aperture || $this->currentPhoto->shutter_speed || $this->currentPhoto->iso)
                                <dl class="space-y-1">
                                    @if ($this->currentPhoto->taken_at)<div class="flex justify-between gap-3"><dt class="text-black/55">Date</dt><dd>{{ $this->currentPhoto->taken_at->format('d/m/Y H:i') }}</dd></div>@endif
                                    @if (trim(($this->currentPhoto->camera_make ?? '').' '.($this->currentPhoto->camera_model ?? '')))<div class="flex justify-between gap-3"><dt class="text-black/55">Appareil</dt><dd>{{ trim(($this->currentPhoto->camera_make ?? '').' '.($this->currentPhoto->camera_model ?? '')) }}</dd></div>@endif
                                    @if ($this->currentPhoto->lens)<div class="flex justify-between gap-3"><dt class="text-black/55">Objectif</dt><dd>{{ $this->currentPhoto->lens }}</dd></div>@endif
                                    @if ($this->currentPhoto->focal_length)<div class="flex justify-between gap-3"><dt class="text-black/55">Focale</dt><dd>{{ $this->currentPhoto->focal_length }} mm</dd></div>@endif
                                    @if ($this->currentPhoto->aperture)<div class="flex justify-between gap-3"><dt class="text-black/55">Ouverture</dt><dd>f/{{ $this->currentPhoto->aperture }}</dd></div>@endif
                                    @if ($this->currentPhoto->shutter_speed)<div class="flex justify-between gap-3"><dt class="text-black/55">Vitesse</dt><dd>{{ $this->currentPhoto->shutter_speed }}</dd></div>@endif
                                    @if ($this->currentPhoto->iso)<div class="flex justify-between gap-3"><dt class="text-black/55">ISO</dt><dd>{{ $this->currentPhoto->iso }}</dd></div>@endif
                                </dl>
                            @else
                                <p class="text-black/50">Aucune donnée EXIF disponible.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Both panels below are fixed overlays on purpose: they must never
             push the halo photo area down or grow the page's scroll height. --}}
        @if ($rejecting)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="cancelReject">
                <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                    <p class="font-semibold">Motif de refus</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($this->rejectionReasons as $reason)
                            <button
                                type="button"
                                wire:click="$set('rejection_reason_id', {{ $reason->id }})"
                                @class(['rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-black/10', 'bg-black text-white' => $rejection_reason_id === $reason->id, 'bg-black/5' => $rejection_reason_id !== $reason->id])
                            >
                                {{ $reason->label }}
                            </button>
                        @endforeach
                    </div>
                    <textarea wire:model="custom_rejection_note" placeholder="Préciser (obligatoire si aucun motif ci-dessus n'est sélectionné)" class="mt-3 w-full rounded-md border border-black/15 p-2 text-sm"></textarea>
                    @error('custom_rejection_note') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="reject" class="rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white">Confirmer le refus</button>
                        <button type="button" wire:click="cancelReject" class="rounded-md border border-black/10 px-4 py-2 text-sm font-semibold">Annuler</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($editing)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="cancelEdit">
                <div class="max-h-[85vh] w-full max-w-lg space-y-4 overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                    <p class="font-semibold">Modifier la photo</p>
                    <label class="block text-sm font-semibold">Ligne
                        <select wire:model.live="line_id" class="mt-1 w-full rounded-md border border-black/15 p-2">
                            <option value="">Toutes les lignes</option>
                            @foreach ($this->availableLines as $line)
                                <option value="{{ $line->id }}" style="background-color: {{ $line->color }}22;">Ligne {{ $line->code }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold">Station
                        <select wire:model.live="station_id" class="mt-1 w-full rounded-md border border-black/15 p-2">
                            <option value="">Choisir une station</option>
                            @foreach ($this->availableStations as $station)
                                <option value="{{ $station->id }}">{{ $station->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold">Accès
                        <select wire:model="station_access_id" class="mt-1 w-full rounded-md border border-black/15 p-2">
                            <option value="">Aucun accès</option>
                            @foreach ($this->availableAccessesForSelectedStation as $access)
                                <option value="{{ $access->id }}">{{ $access->number ? 'N°'.$access->number.' — '.$access->displayName() : $access->displayName() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div>
                        <p class="text-sm font-semibold">Catégories</p>
                        <div class="mt-1 space-y-1">
                            @forelse ($this->selectedCategories as $category)
                                <div class="flex items-center justify-between rounded-md bg-black/5 px-3 py-1.5 text-sm" wire:key="selected-cat-{{ $category->id }}">
                                    <span>{{ $category->name }}</span>
                                    <button type="button" wire:click="removeCategory({{ $category->id }})" class="text-black/50 hover:text-black">
                                        <x-icons.close class="h-4 w-4" />
                                    </button>
                                </div>
                            @empty
                                <p class="text-sm text-black/45">Aucune catégorie sélectionnée.</p>
                            @endforelse
                        </div>
                        <div class="relative mt-2">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="categorySearch"
                                placeholder="Rechercher une catégorie..."
                                class="w-full rounded-md border border-black/15 p-2 text-sm"
                            >
                            @if ($this->categorySearchResults->isNotEmpty())
                                <div class="absolute z-10 mt-1 w-full overflow-hidden rounded-md border border-black/15 bg-white shadow-lg">
                                    @foreach ($this->categorySearchResults as $result)
                                        <button
                                            type="button"
                                            wire:click="addCategory({{ $result->id }})"
                                            wire:key="search-result-{{ $result->id }}"
                                            class="block w-full px-3 py-2 text-left text-sm hover:bg-black/5"
                                        >
                                            {{ $result->parent ? $result->parent->name.' › ' : '' }}{{ $result->name }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    <label class="block text-sm font-semibold">Description
                        <textarea wire:model="description" class="mt-1 w-full rounded-md border border-black/15 p-2"></textarea>
                    </label>
                    <div class="flex gap-2">
                        <button type="button" wire:click="saveEdit" class="rounded-md bg-black px-4 py-2 text-sm font-semibold text-white">Enregistrer et valider</button>
                        <button type="button" wire:click="cancelEdit" class="rounded-md border border-black/10 px-4 py-2 text-sm font-semibold">Annuler</button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
