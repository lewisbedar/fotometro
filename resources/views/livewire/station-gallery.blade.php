<div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5" wire:loading.class="opacity-60">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold">Galerie</h2>
            <p class="mt-1 text-sm text-black/60">{{ $photos->total() }} photographie(s) dans cette sélection</p>
        </div>
        @if ($this->selectedCategory || $this->selectedAccess || $this->selectedLine)
            <button type="button" wire:click="resetFilters" class="rounded-md border border-black/10 px-3 py-2 text-sm font-semibold hover:bg-black hover:text-white">Réinitialiser</button>
        @endif
    </div>

    @if ($this->lineFilters->isNotEmpty())
        <nav class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="Filtrer par ligne">
            <button
                type="button"
                wire:click="selectLine(null)"
                @class(['rounded-full px-3 py-1 font-semibold ring-1 ring-black/10', 'bg-black text-white' => ! $this->selectedLine, 'bg-black/5' => $this->selectedLine])
            >
                Toutes les lignes
            </button>
            @foreach ($this->lineFilters as $item)
                <button
                    type="button"
                    wire:click="selectLine({{ $item['line']->id }})"
                    wire:key="line-{{ $item['line']->id }}"
                    class="flex items-center gap-1.5 rounded-full px-3 py-1 font-semibold ring-1 ring-black/10"
                    @style(["opacity: 0.45" => $this->selectedLine && $this->selectedLine->id !== $item['line']->id])
                >
                    <span class="grid h-5 min-w-5 place-items-center rounded-full px-1 text-[11px] font-bold" style="background: {{ $item['line']->color }}; color: {{ $item['line']->text_color }}">{{ $item['line']->code }}</span>
                    ({{ $item['count'] }})
                </button>
            @endforeach
        </nav>
    @endif

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
                    <span class="mt-1 block text-xs text-black/55">{{ $photo->categories->isNotEmpty() ? $photo->categories->pluck('name')->join(' · ') : 'Sans catégorie' }}</span>
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
