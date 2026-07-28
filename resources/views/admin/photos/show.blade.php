<x-layouts.app title="Photo admin - fotometro">
    <article class="space-y-5 rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
        @if (session('status')) <p class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</p> @endif
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h1 class="text-2xl font-semibold">{{ $photo->title ?: $photo->original_filename }}</h1><p class="text-sm text-black/60">{{ $photo->station->name }} · {{ $photo->processing_status->label() }}</p></div>
            <div class="flex gap-2">
                <a class="rounded-md border border-black/10 px-3 py-2 font-semibold" href="{{ route('admin.photos.edit', $photo) }}">Modifier</a>
                <form method="POST" action="{{ route('admin.photos.process', $photo) }}">@csrf<button class="rounded-md border border-black/10 px-3 py-2 font-semibold">Traiter</button></form>
                <form method="POST" action="{{ route('admin.photos.destroy', $photo) }}" onsubmit="return confirm('Supprimer cette photo ?')">@csrf @method('DELETE')<button class="rounded-md bg-red-700 px-3 py-2 font-semibold text-white">Supprimer</button></form>
            </div>
        </div>
        @if($photo->web_url)<img class="max-h-[70vh] rounded-lg object-contain" src="{{ $photo->web_url }}" alt="">@endif
        <dl class="grid gap-4 text-sm sm:grid-cols-2"><div><dt class="text-black/55">Copyright</dt><dd>{{ $photo->copyright_notice }}</dd></div><div><dt class="text-black/55">Original privé</dt><dd>{{ $photo->original_path }}</dd></div><div><dt class="text-black/55">Erreur</dt><dd>{{ $photo->processing_error ?: '-' }}</dd></div></dl>
    </article>
</x-layouts.app>
