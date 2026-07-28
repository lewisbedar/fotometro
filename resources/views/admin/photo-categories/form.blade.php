<x-layouts.app title="Categorie photo - fotometro">
    <form method="POST" action="{{ $category->exists ? route('admin.photo-categories.update', $category) : route('admin.photo-categories.store') }}" class="mx-auto max-w-2xl space-y-5 rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
        @csrf
        @if ($category->exists) @method('PUT') @endif
        <h1 class="text-2xl font-semibold">{{ $category->exists ? 'Modifier' : 'Creer' }} une categorie</h1>
        @if ($errors->any()) <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div> @endif
        <label class="block text-sm font-semibold">Nom <input name="name" value="{{ old('name', $category->name) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
        <label class="block text-sm font-semibold">Slug <input name="slug" value="{{ old('slug', $category->slug) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
        <label class="block text-sm font-semibold">Parent
            <select name="parent_id" class="mt-1 w-full rounded-md border border-black/15 p-2">
                <option value="">Racine</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected((int) old('parent_id', $category->parent_id) === $parent->id)>{{ $parent->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-semibold">Description <textarea name="description" class="mt-1 w-full rounded-md border border-black/15 p-2">{{ old('description', $category->description) }}</textarea></label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-semibold">Icone <input name="icon" value="{{ old('icon', $category->icon) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
            <label class="block text-sm font-semibold">Ordre <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
        </div>
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))> Active</label>
        <button class="rounded-md bg-black px-4 py-2 font-semibold text-white">Enregistrer</button>
    </form>
</x-layouts.app>
