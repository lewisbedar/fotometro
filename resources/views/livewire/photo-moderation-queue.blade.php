<div
    class="flex h-[calc(100dvh-12rem)] min-h-[420px] flex-col gap-3"
    x-data
    x-on:keydown.window.left="$wire.startReject()"
    x-on:keydown.window.right="$wire.approve()"
    x-on:keydown.window.up="$wire.startEdit()"
    x-on:keydown.window.down="$wire.startEdit()"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p>
            <h1 class="mt-1 text-2xl font-semibold">Modération</h1>
        </div>
        <p class="text-sm text-black/60">{{ $this->pendingCount }} photo(s) en attente</p>
    </div>

    @if (session('status'))
        <p class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</p>
    @endif

    @if (! $this->currentPhoto)
        <div class="flex flex-1 flex-col items-center justify-center rounded-lg bg-white text-center shadow-sm ring-1 ring-black/5">
            <p class="text-lg font-semibold">Aucune photo en attente</p>
            <p class="mt-2 text-sm text-black/60">La file de modération est vide pour le moment.</p>
        </div>
    @else
        <div class="flex min-h-0 flex-none flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-4 py-2 text-sm shadow-sm ring-1 ring-black/5">
            <div class="min-w-0">
                <span class="font-semibold">{{ $this->currentPhoto->station->name }}</span>
                <span class="text-black/60"> · {{ $this->currentPhoto->stationAccess?->displayName() ?? 'Aucun accès' }} · {{ $this->currentPhoto->categories->isNotEmpty() ? $this->currentPhoto->categories->pluck('name')->join(', ') : 'Sans catégorie' }}</span>
                @if ($this->currentPhoto->description)
                    <span class="text-black/70"> — {{ $this->currentPhoto->description }}</span>
                @endif
            </div>
            <span class="flex-none text-xs text-black/45">Soumise par {{ $this->currentPhoto->user?->name ?? 'admin' }} le {{ $this->currentPhoto->created_at->format('d/m/Y') }}</span>
        </div>

        <div class="relative min-h-0 flex-1 overflow-hidden rounded-lg bg-black shadow-sm ring-1 ring-black/5" wire:key="photo-{{ $this->currentPhoto->id }}">
            @if ($this->currentPhoto->web_url)
                <img src="{{ $this->currentPhoto->web_url }}" alt="" class="h-full w-full object-contain">
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
                    <label class="block text-sm font-semibold">Station
                        <select wire:model.live="station_id" class="mt-1 w-full rounded-md border border-black/15 p-2">
                            @foreach ($this->availableStations as $station)
                                <option value="{{ $station->id }}">{{ $station->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold">Accès
                        <select wire:model="station_access_id" class="mt-1 w-full rounded-md border border-black/15 p-2">
                            <option value="">Aucun accès</option>
                            @foreach ($this->availableAccessesForSelectedStation as $access)
                                <option value="{{ $access->id }}">{{ $access->displayName() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div>
                        <p class="text-sm font-semibold">Catégories</p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @foreach ($this->availableCategories as $category)
                                <label class="cursor-pointer select-none rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-black/10 has-[:checked]:bg-black has-[:checked]:text-white hover:bg-black/5">
                                    <input type="checkbox" value="{{ $category->id }}" wire:model="category_ids" class="sr-only">
                                    {{ $category->name }}
                                </label>
                            @endforeach
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
