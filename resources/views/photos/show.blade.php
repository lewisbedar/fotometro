<x-layouts.app
    :title="($photo->title ?: $photo->original_filename).' - fotometro'"
    :description="$metaDescription"
    :canonical="route('photos.show', $photo)"
>
    <article class="space-y-8">
        <a href="{{ route('stations.show', $photo->station) }}" class="text-sm font-medium text-black/65 hover:text-black hover:underline">Retour à {{ $photo->station->name }}</a>
        <header>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Photographie</p>
            <h1 class="mt-2 text-4xl font-semibold">{{ $photo->title ?: $photo->original_filename }}</h1>
            <p class="mt-3 text-black/65">{{ $photo->station->name }} @if($photo->stationAccess) · {{ $photo->stationAccess->name ?: $photo->stationAccess->reference }} @endif</p>
        </header>

        @if ($photo->web_url)
            <figure class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-black/5">
                <img src="{{ $photo->web_url }}" alt="{{ $photo->title ?: $photo->original_filename }}" class="mx-auto max-h-[78vh] rounded object-contain">
                <figcaption class="mt-3 text-sm text-black/65">{{ $photo->copyright_notice }}</figcaption>
            </figure>
        @endif

        <section class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="text-xl font-semibold">Description</h2>
                <p class="mt-3 leading-7 text-black/70">{{ $photo->description ?: 'Aucune description.' }}</p>
            </div>
            <aside class="rounded-lg bg-white p-6 text-sm shadow-sm ring-1 ring-black/5">
                <h2 class="text-xl font-semibold">Détails</h2>
                <dl class="mt-4 space-y-3">
                    <div><dt class="text-black/55">Catégorie</dt><dd>{{ $photo->category?->name ?: '-' }}</dd></div>
                    <div><dt class="text-black/55">Date</dt><dd>{{ $photo->taken_at?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                    <div><dt class="text-black/55">Appareil</dt><dd>{{ trim(($photo->camera_make ?? '').' '.($photo->camera_model ?? '')) ?: '-' }}</dd></div>
                    <div><dt class="text-black/55">Objectif</dt><dd>{{ $photo->lens ?: '-' }}</dd></div>
                    <div><dt class="text-black/55">EXIF</dt><dd>{{ $photo->focal_length ? $photo->focal_length.' mm' : '-' }} · f/{{ $photo->aperture ?: '-' }} · ISO {{ $photo->iso ?: '-' }} · {{ $photo->shutter_speed ?: '-' }}</dd></div>
                    <div><dt class="text-black/55">Licence</dt><dd>{{ $photo->license->label() }}</dd></div>
                    <div><dt class="text-black/55">Conditions</dt><dd>{{ $photo->usage_terms ?: 'Voir la mention de licence.' }}</dd></div>
                </dl>
            </aside>
        </section>

        <nav class="flex justify-between gap-4 text-sm">
            <span>@if($previousPhoto)<a class="font-semibold underline" href="{{ route('photos.show', $previousPhoto) }}">Photo précédente</a>@endif</span>
            <span>@if($nextPhoto)<a class="font-semibold underline" href="{{ route('photos.show', $nextPhoto) }}">Photo suivante</a>@endif</span>
        </nav>
    </article>
</x-layouts.app>
