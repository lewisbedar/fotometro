<x-layouts.app title="Catégories photo - fotométro" :full-width="true">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p>
                <h1 class="mt-2 text-3xl font-semibold">Catégories photo</h1>
            </div>
            <a href="{{ route('admin.photo-categories.create') }}" class="flex items-center gap-2 rounded-md bg-black px-4 py-2 font-semibold text-white"><x-icons.add class="h-4 w-4" /> Nouvelle catégorie</a>
        </div>

        @if ($errors->any())
            <p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</p>
        @endif

        <p class="text-sm text-black/55">Glissez-déposez une catégorie pour la réordonner.</p>

        <div class="space-y-4" x-data="fotometroCategoryDragSort('{{ route('admin.photo-categories.reorder') }}')">
            @foreach ($roots as $root)
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5" data-category-id="{{ $root->id }}">
                    <div
                        class="flex cursor-grab items-center justify-between gap-3 p-4 active:cursor-grabbing"
                        draggable="true"
                        x-on:dragstart="start($event)"
                        x-on:dragover.prevent="over($event)"
                        x-on:drop="drop()"
                    >
                        <div>
                            <p class="font-semibold">{{ $root->name }}</p>
                            <p class="text-sm text-black/55">Racine · {{ $root->is_active ? 'Active' : 'Inactive' }}</p>
                        </div>
                        <div class="flex flex-none gap-2">
                            <a href="{{ route('admin.photo-categories.edit', $root) }}" title="Modifier" class="rounded-md border border-black/10 p-2 hover:bg-black hover:text-white"><x-icons.edit class="h-4 w-4" /></a>
                            <button form="delete-category-{{ $root->id }}" type="submit" title="Supprimer" class="rounded-md bg-red-700 p-2 text-white hover:bg-red-800" onclick="return confirm('Supprimer cette catégorie ?')"><x-icons.trash class="h-4 w-4" /></button>
                        </div>
                    </div>

                    @if ($root->children->isNotEmpty())
                        <div class="divide-y divide-black/10 border-t border-black/10" x-data="fotometroCategoryDragSort('{{ route('admin.photo-categories.reorder') }}')">
                            @foreach ($root->children as $child)
                                <div
                                    class="flex cursor-grab items-center justify-between gap-3 p-3 pl-8 active:cursor-grabbing"
                                    data-category-id="{{ $child->id }}"
                                    draggable="true"
                                    x-on:dragstart.stop="start($event)"
                                    x-on:dragover.stop.prevent="over($event)"
                                    x-on:drop.stop="drop()"
                                >
                                    <span class="text-sm">{{ $child->name }}{{ $child->is_active ? '' : ' · Inactive' }}</span>
                                    <div class="flex flex-none gap-2">
                                        <a href="{{ route('admin.photo-categories.edit', $child) }}" title="Modifier" class="rounded-md border border-black/10 p-2 hover:bg-black hover:text-white"><x-icons.edit class="h-4 w-4" /></a>
                                        <button form="delete-category-{{ $child->id }}" type="submit" title="Supprimer" class="rounded-md bg-red-700 p-2 text-white hover:bg-red-800" onclick="return confirm('Supprimer cette catégorie ?')"><x-icons.trash class="h-4 w-4" /></button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @foreach ($roots as $root)
            <form id="delete-category-{{ $root->id }}" method="POST" action="{{ route('admin.photo-categories.destroy', $root) }}">@csrf @method('DELETE')</form>
            @foreach ($root->children as $child)
                <form id="delete-category-{{ $child->id }}" method="POST" action="{{ route('admin.photo-categories.destroy', $child) }}">@csrf @method('DELETE')</form>
            @endforeach
        @endforeach
    </div>
</x-layouts.app>
