<div
    class="mx-auto max-w-3xl"
    x-data
    x-on:keydown.window.left="$wire.startReject()"
    x-on:keydown.window.right="$wire.approve()"
    x-on:keydown.window.up="$wire.startEdit()"
    x-on:keydown.window.down="$wire.startEdit()"
>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p>
            <h1 class="mt-2 text-3xl font-semibold">Modération</h1>
        </div>
        <p class="text-sm text-black/60">{{ $this->pendingCount }} photo(s) en attente</p>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</p>
    @endif

    @if (! $this->currentPhoto)
        <div class="rounded-lg bg-white p-10 text-center shadow-sm ring-1 ring-black/5">
            <p class="text-lg font-semibold">Aucune photo en attente</p>
            <p class="mt-2 text-sm text-black/60">La file de modération est vide pour le moment.</p>
        </div>
    @else
        <div class="grid grid-cols-[auto_1fr_auto] items-center gap-4">
            <div></div>
            <div class="flex justify-center">
                <button type="button" wire:click="startEdit" title="Modifier (station, accès, catégories, description)" class="rounded-full border border-black/10 bg-white p-3 shadow-sm hover:bg-black hover:text-white">
                    <x-icons.edit class="h-5 w-5" />
                </button>
            </div>
            <div></div>

            <button type="button" wire:click="startReject" title="Refuser" class="rounded-full border border-red-200 bg-white p-4 text-red-700 shadow-sm hover:bg-red-700 hover:text-white">
                <x-icons.close class="h-6 w-6" />
            </button>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5" wire:key="photo-{{ $this->currentPhoto->id }}">
                @if ($this->currentPhoto->web_url)
                    <img src="{{ $this->currentPhoto->web_url }}" alt="" class="mx-auto max-h-[60vh] w-full object-contain">
                @else
                    <div class="flex h-64 items-center justify-center bg-black/5 text-sm text-black/40">Pas d’aperçu</div>
                @endif
                <div class="space-y-1 p-4 text-sm">
                    <p class="font-semibold">{{ $this->currentPhoto->station->name }}</p>
                    <p class="text-black/60">{{ $this->currentPhoto->stationAccess?->displayName() ?? 'Aucun accès' }} · {{ $this->currentPhoto->categories->isNotEmpty() ? $this->currentPhoto->categories->pluck('name')->join(', ') : 'Sans catégorie' }}</p>
                    @if ($this->currentPhoto->description)
                        <p class="text-black/70">{{ $this->currentPhoto->description }}</p>
                    @endif
                    <p class="text-xs text-black/45">Soumise par {{ $this->currentPhoto->user?->name ?? 'admin' }} le {{ $this->currentPhoto->created_at->format('d/m/Y') }}</p>
                </div>
            </div>

            <button type="button" wire:click="approve" title="Approuver" class="rounded-full border border-green-200 bg-white p-4 text-green-700 shadow-sm hover:bg-green-700 hover:text-white">
                <x-icons.check class="h-6 w-6" />
            </button>

            <div></div>
            <div class="flex justify-center">
                <button type="button" wire:click="startEdit" title="Modifier" class="rounded-full border border-black/10 bg-white p-3 shadow-sm hover:bg-black hover:text-white">
                    <x-icons.edit class="h-5 w-5" />
                </button>
            </div>
            <div></div>
        </div>

        @if ($rejecting)
            <div class="mt-6 rounded-lg bg-white p-4 shadow-sm ring-1 ring-black/5">
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
        @endif

        @if ($editing)
            <div class="mt-6 space-y-4 rounded-lg bg-white p-4 shadow-sm ring-1 ring-black/5">
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
        @endif
    @endif
</div>
