<x-layouts.app title="Categories photo - fotométro">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p>
                <h1 class="mt-2 text-3xl font-semibold">Categories photo</h1>
            </div>
            <a href="{{ route('admin.photo-categories.create') }}" class="rounded-md bg-black px-4 py-2 font-semibold text-white">Nouvelle categorie</a>
        </div>
        @if (session('status')) <p class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</p> @endif
        <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5">
            <table class="w-full text-left text-sm">
                <thead class="bg-black/5"><tr><th class="p-3">Nom</th><th>Parent</th><th>Active</th><th></th></tr></thead>
                <tbody class="divide-y divide-black/10">
                    @foreach ($categories as $category)
                        <tr>
                            <td class="p-3 font-semibold">{{ $category->name }}</td>
                            <td>{{ $category->parent?->name ?: 'Racine' }}</td>
                            <td>{{ $category->is_active ? 'Oui' : 'Non' }}</td>
                            <td class="p-3 text-right"><a class="font-semibold underline" href="{{ route('admin.photo-categories.edit', $category) }}">Modifier</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $categories->links() }}
    </div>
</x-layouts.app>
