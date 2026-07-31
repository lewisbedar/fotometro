<x-layouts.app title="Motif de refus - fotométro" :full-width="true">
    <form method="POST" action="{{ $reason->exists ? route('admin.photo-rejection-reasons.update', $reason) : route('admin.photo-rejection-reasons.store') }}" class="mx-auto max-w-2xl space-y-5 rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
        @csrf
        @if ($reason->exists) @method('PUT') @endif
        <h1 class="text-2xl font-semibold">{{ $reason->exists ? 'Modifier' : 'Créer' }} un motif de refus</h1>
        @if ($errors->any()) <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div> @endif
        <label class="block text-sm font-semibold">Libellé <input name="label" value="{{ old('label', $reason->label) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
        <label class="block text-sm font-semibold">Slug <input name="slug" value="{{ old('slug', $reason->slug) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
        <label class="block text-sm font-semibold">Ordre <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $reason->sort_order ?? 0) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $reason->is_active ?? true))> Actif</label>
        <button class="rounded-md bg-black px-4 py-2 font-semibold text-white">Enregistrer</button>
    </form>
</x-layouts.app>
