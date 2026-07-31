<x-layouts.app :title="($photo->title ?: $photo->original_filename).' - fotométro'" :full-width="true">
    <div class="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
        @if ($errors->any()) <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div> @endif

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ $photo->title ?: $photo->original_filename }}</h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <span class="ratp-sign-mini"><span class="ratp-sign-mini-plate"><span class="ratp-sign-mini-text">{{ $photo->station->name }}</span></span></span>
                    @foreach ($photo->station->lines as $line)
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" style="background: {{ $line->color }}; color: {{ $line->text_color }}">{{ $line->code }}</span>
                    @endforeach
                    <span class="text-sm text-black/60">· {{ $photo->adminStatusLabel() }}</span>
                </div>
                @if ($photo->station->cover_photo_id === $photo->id)
                    <span class="mt-1 inline-block rounded-full bg-black px-2 py-0.5 text-xs font-semibold text-white">Photo de couverture</span>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($photo->is_published)
                    <button form="unpublish-photo" class="flex items-center gap-2 rounded-md border border-black/10 px-3 py-2 font-semibold">Dépublier</button>
                @else
                    <button form="publish-photo" class="flex items-center gap-2 rounded-md bg-green-700 px-3 py-2 font-semibold text-white hover:bg-green-800">Publier</button>
                @endif
                <button form="process-photo" class="flex items-center gap-2 rounded-md border border-black/10 px-3 py-2 font-semibold"><x-icons.refresh class="h-4 w-4" /> Traiter</button>
                @if ($photo->station->cover_photo_id === $photo->id)
                    <button form="unset-cover-photo" class="flex items-center gap-2 rounded-md border border-black/10 px-3 py-2 font-semibold"><x-icons.star class="h-4 w-4" /> Retirer la couverture</button>
                @elseif ($photo->is_published && $photo->processing_status === \App\Enums\PhotoProcessingStatus::Ready)
                    <button form="set-cover-photo" class="flex items-center gap-2 rounded-md border border-black/10 px-3 py-2 font-semibold"><x-icons.star class="h-4 w-4" /> Définir comme couverture</button>
                @endif
                <button form="delete-photo" class="flex items-center gap-2 rounded-md bg-red-700 px-3 py-2 font-semibold text-white" onclick="return confirm('Supprimer cette photo ?')"><x-icons.trash class="h-4 w-4" /> Supprimer</button>
            </div>
        </div>

        @if ($photo->processing_error)
            <p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $photo->processing_error }}</p>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1fr_420px]">
            <div class="lg:sticky lg:top-6 lg:self-start">
                @if ($photo->web_url)
                    <img class="max-h-[75vh] w-full rounded-lg object-contain" src="{{ $photo->web_url }}" alt="">
                @else
                    <div class="flex h-64 w-full items-center justify-center rounded-lg bg-black/5 text-sm text-black/40">Pas d’aperçu</div>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.photos.update', $photo) }}" class="space-y-4">
                @csrf @method('PUT')

                <div class="space-y-4 rounded-lg border border-black/10 bg-black/[0.02] p-4">
                    <h2 class="text-base font-semibold">Informations</h2>
                    <label class="block text-sm font-semibold">Titre <input name="title" value="{{ old('title', $photo->title) }}" class="mt-1 w-full rounded-md border border-black/15 bg-white p-2"></label>
                    <label class="block text-sm font-semibold">Description <textarea name="description" class="mt-1 w-full rounded-md border border-black/15 bg-white p-2">{{ old('description', $photo->description) }}</textarea></label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-semibold">Date <input type="datetime-local" name="taken_at" value="{{ old('taken_at', $photo->taken_at?->format('Y-m-d\\TH:i')) }}" class="mt-1 w-full rounded-md border border-black/15 bg-white p-2"></label>
                        <label class="block text-sm font-semibold">Ordre <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $photo->sort_order) }}" class="mt-1 w-full rounded-md border border-black/15 bg-white p-2"></label>
                    </div>
                </div>

                @include('admin.photos.partials.location-selector')

                <div class="space-y-3 rounded-lg border border-black/10 bg-black/[0.02] p-4">
                    <h2 class="text-base font-semibold">Catégories</h2>
                    <x-category-checklist
                        :categories="$categories"
                        name="category_ids"
                        :selected="old('category_ids', $photo->categories->pluck('id')->all())"
                    />
                </div>

                <div class="space-y-4 rounded-lg border border-black/10 bg-black/[0.02] p-4" x-data="{ license: '{{ old('license', $photo->license->value) }}' }">
                    <h2 class="text-base font-semibold">Droits &amp; licence</h2>
                    <label class="block text-sm font-semibold">Titulaire <input name="copyright_holder" value="{{ old('copyright_holder', $photo->copyright_holder) }}" class="mt-1 w-full rounded-md border border-black/15 bg-white p-2"></label>
                    <label class="block text-sm font-semibold">Mention <input name="copyright_notice" value="{{ old('copyright_notice', $photo->copyright_notice) }}" class="mt-1 w-full rounded-md border border-black/15 bg-white p-2"></label>
                    <label class="block text-sm font-semibold">Licence
                        <select name="license" class="mt-1 w-full rounded-md border border-black/15 bg-white p-2" x-model="license">
                            @foreach($licenses as $license)
                                <option value="{{ $license->value }}" @selected(old('license', $photo->license->value) === $license->value)>{{ $license->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold" x-show="license === 'custom'" x-cloak>
                        Conditions d’utilisation
                        <textarea name="usage_terms" class="mt-1 w-full rounded-md border border-black/15 bg-white p-2" placeholder="Décrivez les conditions de cette licence personnalisée.">{{ old('usage_terms', $photo->usage_terms) }}</textarea>
                    </label>
                </div>

                <button class="w-full rounded-md bg-black px-4 py-2 font-semibold text-white">Enregistrer</button>
            </form>
        </div>
    </div>

    <form id="process-photo" method="POST" action="{{ route('admin.photos.process', $photo) }}">@csrf</form>
    @if ($photo->is_published)
        <form id="unpublish-photo" method="POST" action="{{ route('admin.photos.unpublish', $photo) }}">@csrf</form>
    @else
        <form id="publish-photo" method="POST" action="{{ route('admin.photos.publish', $photo) }}">@csrf</form>
    @endif
    @if ($photo->station->cover_photo_id === $photo->id)
        <form id="unset-cover-photo" method="POST" action="{{ route('admin.photos.unset-cover', $photo) }}">@csrf @method('DELETE')</form>
    @elseif ($photo->is_published && $photo->processing_status === \App\Enums\PhotoProcessingStatus::Ready)
        <form id="set-cover-photo" method="POST" action="{{ route('admin.photos.set-cover', $photo) }}">@csrf</form>
    @endif
    <form id="delete-photo" method="POST" action="{{ route('admin.photos.destroy', $photo) }}">@csrf @method('DELETE')</form>
</x-layouts.app>
