<x-layouts.app title="Catégories photo - fotométro">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p>
                <h1 class="mt-2 text-3xl font-semibold">Catégories photo</h1>
            </div>
            <a href="{{ route('admin.photo-categories.create') }}" class="flex items-center gap-2 rounded-md bg-black px-4 py-2 font-semibold text-white"><x-icons.add class="h-4 w-4" /> Nouvelle catégorie</a>
        </div>
        <div class="divide-y divide-black/10 overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5">
            @foreach ($categories as $category)
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div>
                        <p class="font-semibold">{{ $category->name }}</p>
                        <p class="text-sm text-black/55">{{ $category->parent?->name ?: 'Racine' }} · {{ $category->is_active ? 'Active' : 'Inactive' }}</p>
                    </div>
                    <a href="{{ route('admin.photo-categories.edit', $category) }}" title="Modifier" class="rounded-md border border-black/10 p-2 hover:bg-black hover:text-white"><x-icons.edit class="h-4 w-4" /></a>
                </div>
            @endforeach
        </div>
        {{ $categories->links() }}
    </div>
</x-layouts.app>
