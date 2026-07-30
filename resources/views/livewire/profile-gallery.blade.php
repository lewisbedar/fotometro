<div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5" wire:loading.class="opacity-60">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Photographies</h2>
            <p class="mt-1 text-sm text-black/60">{{ $photos->total() }} photographie(s) publiée(s)</p>
        </div>
        <select wire:model.live="sort" class="rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-black">
            <option value="recent">Les plus récentes</option>
            <option value="oldest">Les plus anciennes</option>
            <option value="popular">Popularité</option>
        </select>
    </div>

    @if ($this->categoryFilters->isNotEmpty())
        <nav class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="Filtres de galerie">
            <button
                type="button"
                wire:click="selectCategory(null)"
                @class(['rounded-full px-3 py-1 font-semibold ring-1 ring-black/10', 'bg-black text-white' => ! $this->selectedCategory, 'bg-black/5' => $this->selectedCategory])
            >
                Toutes ({{ $this->allPhotos->count() }})
            </button>
            @foreach ($this->categoryFilters as $item)
                <button
                    type="button"
                    wire:click="selectCategory('{{ $item['category']->slug }}')"
                    wire:key="category-{{ $item['category']->id }}"
                    @class(['rounded-full px-3 py-1 font-semibold ring-1 ring-black/10', 'bg-black text-white' => $this->selectedCategory?->id === $item['category']->id, 'bg-black/5' => $this->selectedCategory?->id !== $item['category']->id])
                >
                    {{ $item['category']->name }} ({{ $item['count'] }})
                </button>
            @endforeach
        </nav>

        @if ($this->subCategoryFilters->isNotEmpty())
            <div class="mt-4 border-t border-black/10 pt-4">
                <p class="text-sm font-semibold">{{ $this->selectedCategory->name }}</p>
                <div class="mt-2 flex flex-wrap gap-2 text-sm">
                    @foreach ($this->subCategoryFilters as $item)
                        <button
                            type="button"
                            wire:click="selectCategory('{{ $item['category']->slug }}')"
                            wire:key="subcategory-{{ $item['category']->id }}"
                            @class(['rounded-full px-3 py-1 font-semibold ring-1 ring-black/10', 'bg-black text-white' => $this->selectedCategory?->id === $item['category']->id, 'bg-white hover:bg-black hover:text-white' => $this->selectedCategory?->id !== $item['category']->id])
                        >
                            {{ $item['category']->name }} ({{ $item['count'] }})
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($photos as $photo)
            <x-photo-link :photo="$photo" wire:key="photo-{{ $photo->id }}" class="group overflow-hidden rounded-md bg-black/[0.03] ring-1 ring-black/10 transition hover:-translate-y-0.5 hover:shadow-md">
                @if ($photo->thumbnail_url)
                    <img src="{{ $photo->thumbnail_url }}" alt="{{ $photo->publicLabel() }}" class="aspect-[4/3] w-full object-cover transition group-hover:scale-105">
                @else
                    <span class="grid aspect-[4/3] place-items-center bg-black/5 text-sm text-black/55">Aperçu indisponible</span>
                @endif
                <span class="block p-3">
                    <span class="block truncate text-sm font-semibold">{{ $photo->publicLabel() }}</span>
                    <span class="mt-1 flex items-center justify-between gap-2 text-xs text-black/55">
                        <span class="truncate">{{ $photo->station?->name }}</span>
                        <span class="flex-none">{{ $photo->views_count }} vue{{ $photo->views_count == 1 ? '' : 's' }}</span>
                    </span>
                </span>
            </x-photo-link>
        @empty
            <p class="col-span-full rounded-md bg-black/5 p-4 text-sm text-black/65">Aucune photographie ne correspond à ces filtres.</p>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $photos->links() }}
    </div>
</div>
