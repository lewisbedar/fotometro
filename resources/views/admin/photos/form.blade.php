<x-layouts.app title="Modifier photo - fotometro">
    <form method="POST" action="{{ route('admin.photos.update', $photo) }}" class="mx-auto max-w-3xl space-y-5 rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
        @csrf @method('PUT')
        <h1 class="text-2xl font-semibold">Modifier la photo</h1>
        @if ($errors->any()) <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div> @endif
        <label class="block text-sm font-semibold">Titre <input name="title" value="{{ old('title', $photo->title) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
        <label class="block text-sm font-semibold">Description <textarea name="description" class="mt-1 w-full rounded-md border border-black/15 p-2">{{ old('description', $photo->description) }}</textarea></label>
        @include('admin.photos.partials.location-selector')
        <label class="block text-sm font-semibold">Catégorie <select name="photo_category_id" class="mt-1 w-full rounded-md border border-black/15 p-2"><option value="">Aucune</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('photo_category_id', $photo->photo_category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-semibold">Date <input type="datetime-local" name="taken_at" value="{{ old('taken_at', $photo->taken_at?->format('Y-m-d\\TH:i')) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
            <label class="block text-sm font-semibold">Ordre <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $photo->sort_order) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
        </div>
        <label class="block text-sm font-semibold">Titulaire <input name="copyright_holder" value="{{ old('copyright_holder', $photo->copyright_holder) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
        <label class="block text-sm font-semibold">Mention <input name="copyright_notice" value="{{ old('copyright_notice', $photo->copyright_notice) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
        <label class="block text-sm font-semibold">Licence <select name="license" class="mt-1 w-full rounded-md border border-black/15 p-2">@foreach($licenses as $license)<option value="{{ $license->value }}" @selected(old('license', $photo->license->value) === $license->value)>{{ $license->label() }}</option>@endforeach</select></label>
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $photo->is_featured))> Mise en avant</label>
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="publish_when_ready" value="1" @checked(old('publish_when_ready', $photo->publish_when_ready))> Publier automatiquement quand le traitement réussit</label>
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $photo->is_published))> Publiée</label>
        <button class="rounded-md bg-black px-4 py-2 font-semibold text-white">Enregistrer</button>
    </form>
</x-layouts.app>
