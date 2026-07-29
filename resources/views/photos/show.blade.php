<x-layouts.app
    :title="$photo->publicLabel().' - fotométro'"
    :description="$metaDescription"
    :canonical="route('photos.show', $photo)"
>
    <article class="space-y-8">
        <a href="{{ route('stations.show', $photo->station) }}" class="text-sm font-medium text-black/65 hover:text-black hover:underline">Retour à {{ $photo->station->name }}</a>

        <header>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Photographie</p>
            <h1 class="mt-2 text-4xl font-semibold">{{ $photo->publicLabel() }}</h1>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="ratp-sign-mini"><span class="ratp-sign-mini-plate"><span class="ratp-sign-mini-text">{{ $photo->station->name }}</span></span></span>
                @foreach ($photo->station->lines as $line)
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" style="background: {{ $line->color }}; color: {{ $line->text_color }}">{{ $line->code }}</span>
                @endforeach
                @if($photo->stationAccess)
                    <span class="text-black/65">· {{ $photo->stationAccess->displayName() }}</span>
                @endif
            </div>
        </header>

        @if ($photo->web_url)
            <figure class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-black/5">
                <img src="{{ $photo->web_url }}" alt="{{ $photo->publicLabel() }}" class="mx-auto max-h-[78vh] rounded object-contain">
                <figcaption class="mt-3 text-sm text-black/65">{{ $photo->copyright_notice }}</figcaption>
            </figure>
        @endif

        <section class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="text-xl font-semibold">Description</h2>
                <p class="mt-3 leading-7 text-black/70">{{ $photo->description ?: 'Aucune description.' }}</p>
            </div>

            <aside class="space-y-4">
                @if ($photo->taken_at || $photo->camera_make || $photo->camera_model || $photo->lens || $photo->focal_length || $photo->aperture || $photo->shutter_speed || $photo->iso)
                    <section class="rounded-lg bg-white p-6 text-sm shadow-sm ring-1 ring-black/5">
                        <h2 class="text-xl font-semibold">Prise de vue</h2>
                        <dl class="mt-4 space-y-3">
                            @if ($photo->taken_at)<div><dt class="text-black/55">Date</dt><dd>{{ $photo->taken_at->format('d/m/Y H:i') }}</dd></div>@endif
                            @if (trim(($photo->camera_make ?? '').' '.($photo->camera_model ?? '')))<div><dt class="text-black/55">Appareil</dt><dd>{{ trim(($photo->camera_make ?? '').' '.($photo->camera_model ?? '')) }}</dd></div>@endif
                            @if ($photo->lens)<div><dt class="text-black/55">Objectif</dt><dd>{{ $photo->lens }}</dd></div>@endif
                            @if ($photo->focal_length)<div><dt class="text-black/55">Focale</dt><dd>{{ $photo->focal_length }} mm</dd></div>@endif
                            @if ($photo->aperture)<div><dt class="text-black/55">Ouverture</dt><dd>f/{{ $photo->aperture }}</dd></div>@endif
                            @if ($photo->shutter_speed)<div><dt class="text-black/55">Vitesse</dt><dd>{{ $photo->shutter_speed }}</dd></div>@endif
                            @if ($photo->iso)<div><dt class="text-black/55">ISO</dt><dd>{{ $photo->iso }}</dd></div>@endif
                        </dl>
                    </section>
                @endif

                <section class="rounded-lg bg-white p-6 text-sm shadow-sm ring-1 ring-black/5">
                    <h2 class="text-xl font-semibold">Localisation</h2>
                    <dl class="mt-4 space-y-3">
                        <div><dt class="text-black/55">Station</dt><dd><a class="font-semibold underline" href="{{ route('stations.show', $photo->station) }}">{{ $photo->station->name }}</a></dd></div>
                        @if ($photo->stationAccess)<div><dt class="text-black/55">Accès</dt><dd>{{ $photo->stationAccess->displayName() }}</dd></div>@endif
                        @if ($photo->categories->isNotEmpty())<div><dt class="text-black/55">Catégories</dt><dd>{{ $photo->categories->pluck('name')->join(', ') }}</dd></div>@endif
                    </dl>
                </section>

                <section class="rounded-lg bg-white p-6 text-sm shadow-sm ring-1 ring-black/5">
                    <h2 class="text-xl font-semibold">Droits</h2>
                    <dl class="mt-4 space-y-3">
                        <div><dt class="text-black/55">Auteur</dt><dd>{{ $photo->copyright_holder }}</dd></div>
                        <div><dt class="text-black/55">Copyright</dt><dd>{{ $photo->copyright_notice }}</dd></div>
                        <div><dt class="text-black/55">Licence</dt><dd>{{ $photo->license->label() }}</dd></div>
                        @if ($photo->usage_terms)<div><dt class="text-black/55">Conditions</dt><dd>{{ $photo->usage_terms }}</dd></div>@endif
                    </dl>
                </section>
            </aside>
        </section>

        <nav class="flex justify-between gap-4 text-sm">
            <span>@if($previousPhoto)<a class="font-semibold underline" href="{{ route('photos.show', $previousPhoto) }}">Photo précédente</a>@endif</span>
            <span>@if($nextPhoto)<a class="font-semibold underline" href="{{ route('photos.show', $nextPhoto) }}">Photo suivante</a>@endif</span>
        </nav>
    </article>
</x-layouts.app>
