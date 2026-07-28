<x-layouts.app title="Photos - fotometro" :full-width="true">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div><p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p><h1 class="mt-2 text-3xl font-semibold">Photos</h1></div>
            <a href="{{ route('admin.photos.import') }}" class="rounded-md bg-black px-4 py-2 font-semibold text-white">Importer</a>
        </div>
        @if (session('status')) <p class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</p> @endif
        <section class="grid gap-3 rounded-lg bg-white p-4 text-sm shadow-sm ring-1 ring-black/5 sm:grid-cols-5">
            <div><p class="text-black/55">Publiées</p><p class="text-2xl font-semibold">{{ $photoStats['published'] }}</p></div>
            <div><p class="text-black/55">Brouillons</p><p class="text-2xl font-semibold">{{ $photoStats['drafts'] }}</p></div>
            <div><p class="text-black/55">En attente</p><p class="text-2xl font-semibold">{{ $photoStats['pending'] }}</p></div>
            <div><p class="text-black/55">En erreur</p><p class="text-2xl font-semibold">{{ $photoStats['failed'] }}</p></div>
            <div class="flex items-center"><a class="rounded-md bg-black px-3 py-2 font-semibold text-white" href="{{ route('admin.photos.import') }}">Ajouter des photos</a></div>
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
            <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5">
                <table class="w-full text-left text-sm">
                    <thead class="bg-black/5"><tr><th class="p-3"></th><th>Photo</th><th>Station</th><th>Catégorie</th><th>État</th><th>Actions</th></tr></thead>
                    <tbody class="divide-y divide-black/10">
                        @foreach ($photos as $photo)
                            <tr>
                                <td class="p-3"><input type="checkbox" name="photo_ids[]" value="{{ $photo->id }}"></td>
                                <td class="p-3"><div class="flex items-center gap-3">@if($photo->thumbnail_url)<img class="h-14 w-20 rounded object-cover" src="{{ $photo->thumbnail_url }}" alt="">@endif <span>{{ $photo->title ?: $photo->original_filename }}</span></div></td>
                                <td>{{ $photo->station?->name }}</td>
                                <td>{{ $photo->category?->name ?: '-' }}</td>
                                <td><span class="rounded-full bg-black/5 px-2 py-1 text-xs font-semibold">{{ $photo->adminStatusLabel() }}</span></td>
                                <td class="p-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a class="rounded-md border border-black/10 px-2 py-1 font-semibold" href="{{ route('admin.photos.edit', $photo) }}">Modifier</a>
                                        @if ($photo->processing_status === \App\Enums\PhotoProcessingStatus::Pending)
                                            <button form="process-photo-{{ $photo->id }}" class="rounded-md border border-black/10 px-2 py-1 font-semibold">Traiter maintenant</button>
                                        @elseif ($photo->processing_status === \App\Enums\PhotoProcessingStatus::Failed)
                                            <button form="process-photo-{{ $photo->id }}" class="rounded-md border border-black/10 px-2 py-1 font-semibold">Réessayer</button>
                                        @elseif ($photo->processing_status === \App\Enums\PhotoProcessingStatus::Ready && ! $photo->is_published)
                                            <button form="publish-photo-{{ $photo->id }}" class="rounded-md border border-black/10 px-2 py-1 font-semibold">Publier</button>
                                        @elseif ($photo->processing_status === \App\Enums\PhotoProcessingStatus::Ready && $photo->is_published)
                                            <button form="unpublish-photo-{{ $photo->id }}" class="rounded-md border border-black/10 px-2 py-1 font-semibold">Dépublier</button>
                                        @endif
                                        <button form="delete-photo-{{ $photo->id }}" class="rounded-md bg-red-700 px-2 py-1 font-semibold text-white" onclick="return confirm('Supprimer cette photo ?')">Supprimer</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
