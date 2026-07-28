<x-layouts.app title="Administration - fotométro">
    <div class="space-y-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p>
            <h1 class="mt-2 text-3xl font-semibold">Tableau de bord</h1>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">Lignes</p>
                <p class="mt-2 text-4xl font-semibold">{{ $lineCount }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">Stations</p>
                <p class="mt-2 text-4xl font-semibold">{{ $stationCount }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">Stations photographiées</p>
                <p class="mt-2 text-4xl font-semibold">{{ $documentedStationCount }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">Non documentées</p>
                <p class="mt-2 text-4xl font-semibold">{{ $undocumentedStationCount }}</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">Photos totales</p>
                <p class="mt-2 text-4xl font-semibold">{{ $photoCount }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">Photos prêtes</p>
                <p class="mt-2 text-4xl font-semibold">{{ $readyPhotoCount }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">En attente / erreur</p>
                <p class="mt-2 text-4xl font-semibold">{{ $pendingPhotoCount }} / {{ $failedPhotoCount }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-black/55">Publiées / stations</p>
                <p class="mt-2 text-4xl font-semibold">{{ $publishedPhotoCount }} / {{ $stationsWithPhotosCount }}</p>
            </div>
        </div>

        <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold">Catalogue photo</h2>
                <div class="flex gap-2 text-sm">
                    <a class="rounded-md border border-black/10 px-3 py-2 font-semibold hover:bg-black hover:text-white" href="{{ route('admin.photo-categories.index') }}">Catégories</a>
                    <a class="rounded-md border border-black/10 px-3 py-2 font-semibold hover:bg-black hover:text-white" href="{{ route('admin.photos.index') }}">Photos</a>
                    <a class="rounded-md bg-black px-3 py-2 font-semibold text-white" href="{{ route('admin.photos.import') }}">Importer</a>
                </div>
            </div>
            <div class="mt-4 divide-y divide-black/10">
                @forelse ($latestPhotos as $photo)
                    <a class="flex items-center justify-between gap-3 py-3 text-sm hover:bg-black/5" href="{{ route('admin.photos.show', $photo) }}">
                        <span>{{ $photo->title ?: $photo->original_filename }} · {{ $photo->station?->name }}</span>
                        <span class="text-black/55">{{ $photo->processing_status->label() }}</span>
                    </a>
                @empty
                    <p class="py-3 text-sm text-black/60">Aucune photo importée.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
            <h2 class="text-xl font-semibold">Traitement des photos</h2>
            <div class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                <div><p class="text-black/55">En attente</p><p class="text-3xl font-semibold">{{ $pendingPhotoCount }}</p></div>
                <div><p class="text-black/55">En cours</p><p class="text-3xl font-semibold">{{ $processingPhotoCount }}</p></div>
                <div><p class="text-black/55">En erreur</p><p class="text-3xl font-semibold">{{ $failedPhotoCount }}</p></div>
            </div>
            <p class="mt-3 text-sm text-black/60">Les photos en attente sont traitées automatiquement par la tâche planifiée.</p>
        </div>
    </div>
</x-layouts.app>
