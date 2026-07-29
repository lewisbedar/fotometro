<x-layouts.app title="Photos - fotométro" :full-width="true">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div><p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p><h1 class="mt-2 text-3xl font-semibold">Photos</h1></div>
            <a href="{{ route('admin.photos.import') }}" class="flex items-center gap-2 rounded-md bg-black px-4 py-2 font-semibold text-white"><x-icons.add class="h-4 w-4" /> Importer</a>
        </div>
        @if (session('status')) <p class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</p> @endif
        <section class="grid gap-3 rounded-lg bg-white p-4 text-sm shadow-sm ring-1 ring-black/5 sm:grid-cols-4">
            <div><p class="text-black/55">Publiées</p><p class="text-2xl font-semibold">{{ $photoStats['published'] }}</p></div>
            <div><p class="text-black/55">Brouillons</p><p class="text-2xl font-semibold">{{ $photoStats['drafts'] }}</p></div>
            <div><p class="text-black/55">En attente</p><p class="text-2xl font-semibold">{{ $photoStats['pending'] }}</p></div>
            <div><p class="text-black/55">En erreur</p><p class="text-2xl font-semibold">{{ $photoStats['failed'] }}</p></div>
        </section>
        @if (session('imported_photo_ids'))
            <div class="flex flex-wrap gap-2 rounded-lg bg-white p-4 text-sm shadow-sm ring-1 ring-black/5">
                <a class="rounded-md border border-black/10 px-3 py-2 font-semibold" href="{{ route('admin.photos.index') }}">Voir les photos importées</a>
                @if (config('fotometro.photos.process_synchronously'))
                    <form method="POST" action="{{ route('admin.photos.bulk') }}">
                        @csrf
                        <input type="hidden" name="bulk_action" value="process">
                        @foreach (session('imported_photo_ids') as $id)
                            <input type="hidden" name="photo_ids[]" value="{{ $id }}">
                        @endforeach
                        <button class="rounded-md bg-black px-3 py-2 font-semibold text-white">Traiter maintenant</button>
                    </form>
                @endif
            </div>
        @endif
        <form class="grid gap-3 rounded-lg bg-white p-4 text-sm shadow-sm ring-1 ring-black/5 md:grid-cols-5">
            <input name="q" value="{{ request('q') }}" placeholder="Recherche" class="rounded-md border border-black/15 p-2">
            <select name="station_id" class="rounded-md border border-black/15 p-2"><option value="">Station</option>@foreach($stations as $station)<option value="{{ $station->id }}" @selected(request('station_id') == $station->id)>{{ $station->name }}</option>@endforeach</select>
            <select name="category_id" class="rounded-md border border-black/15 p-2"><option value="">Catégorie</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select>
            <select name="processing_status" class="rounded-md border border-black/15 p-2"><option value="">Statut</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('processing_status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
            <button class="rounded-md bg-black px-3 py-2 font-semibold text-white">Filtrer</button>
        </form>

        <form method="POST" action="{{ route('admin.photos.bulk') }}" class="space-y-3">
            @csrf
            <div class="flex flex-wrap items-center gap-2 rounded-lg bg-white p-3 text-sm shadow-sm ring-1 ring-black/5">
                <select name="bulk_action" class="rounded-md border border-black/15 p-2">
                    <option value="publish">Publier</option>
                    <option value="unpublish">Dépublier</option>
                    <option value="process">Traiter</option>
                    <option value="retry">Réessayer</option>
                    <option value="delete">Supprimer</option>
                </select>
                <button class="rounded-md bg-black px-3 py-2 font-semibold text-white">Appliquer au lot sélectionné</button>
                <p class="text-black/55">Traitement immédiat limité à {{ config('fotometro.photos.manual_process_limit') }} photos.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($photos as $photo)
                    <div class="relative overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5">
                        <label class="absolute left-2 top-2 z-10 flex h-6 w-6 items-center justify-center rounded-md bg-white/90 shadow-sm">
                            <input type="checkbox" name="photo_ids[]" value="{{ $photo->id }}" aria-label="Sélectionner cette photo">
                        </label>
                        <a href="{{ route('admin.photos.show', $photo) }}" title="Voir la photo en grand">
                            @if ($photo->thumbnail_url)
                                <img class="h-40 w-full object-cover" src="{{ $photo->thumbnail_url }}" alt="">
                            @else
                                <div class="flex h-40 w-full items-center justify-center bg-black/5 text-xs text-black/40">Pas d’aperçu</div>
                            @endif
                        </a>
                        <div class="space-y-2 p-3">
                            <p class="truncate text-sm font-semibold" title="{{ $photo->title ?: $photo->original_filename }}">{{ $photo->title ?: $photo->original_filename }}</p>
                            <p class="truncate text-xs text-black/55">{{ $photo->station?->name }}{{ $photo->categories->isNotEmpty() ? ' · '.$photo->categories->pluck('name')->join(', ') : '' }}</p>
                            <span class="inline-block rounded-full bg-black/5 px-2 py-1 text-xs font-semibold">{{ $photo->adminStatusLabel() }}</span>
                            <div class="flex flex-wrap items-center gap-1 pt-1">
                                <a href="{{ route('admin.photos.show', $photo) }}" title="Modifier" class="rounded-md border border-black/10 p-1.5 hover:bg-black hover:text-white"><x-icons.edit class="h-4 w-4" /></a>
                                @if ($photo->processing_status === \App\Enums\PhotoProcessingStatus::Pending)
                                    <button form="process-photo-{{ $photo->id }}" title="Traiter maintenant" class="rounded-md border border-black/10 p-1.5 hover:bg-black hover:text-white"><x-icons.refresh class="h-4 w-4" /></button>
                                @elseif ($photo->processing_status === \App\Enums\PhotoProcessingStatus::Failed)
                                    <button form="process-photo-{{ $photo->id }}" title="Réessayer" class="rounded-md border border-black/10 p-1.5 hover:bg-black hover:text-white"><x-icons.refresh class="h-4 w-4" /></button>
                                @elseif ($photo->processing_status === \App\Enums\PhotoProcessingStatus::Ready && ! $photo->is_published)
                                    <button form="publish-photo-{{ $photo->id }}" title="Publier" class="rounded-md border border-black/10 p-1.5 hover:bg-black hover:text-white"><x-icons.visibility class="h-4 w-4" /></button>
                                @elseif ($photo->processing_status === \App\Enums\PhotoProcessingStatus::Ready && $photo->is_published)
                                    <button form="unpublish-photo-{{ $photo->id }}" title="Dépublier" class="rounded-md border border-black/10 p-1.5 hover:bg-black hover:text-white"><x-icons.visibility-off class="h-4 w-4" /></button>
                                @endif
                                <button form="delete-photo-{{ $photo->id }}" title="Supprimer" class="ml-auto rounded-md bg-red-700 p-1.5 text-white hover:bg-red-800" onclick="return confirm('Supprimer cette photo ?')"><x-icons.trash class="h-4 w-4" /></button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </form>

        @foreach ($photos as $photo)
            <form id="process-photo-{{ $photo->id }}" method="POST" action="{{ route('admin.photos.process', $photo) }}">@csrf</form>
            <form id="publish-photo-{{ $photo->id }}" method="POST" action="{{ route('admin.photos.publish', $photo) }}">@csrf</form>
            <form id="unpublish-photo-{{ $photo->id }}" method="POST" action="{{ route('admin.photos.unpublish', $photo) }}">@csrf</form>
            <form id="delete-photo-{{ $photo->id }}" method="POST" action="{{ route('admin.photos.destroy', $photo) }}">@csrf @method('DELETE')</form>
        @endforeach

        {{ $photos->links() }}
    </div>
</x-layouts.app>
