<x-layouts.app title="Importer des photos - fotometro">
    <form method="POST" action="{{ route('admin.photos.store') }}" enctype="multipart/form-data" class="mx-auto max-w-3xl space-y-5 rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
        @csrf
        <h1 class="text-2xl font-semibold">Importer des photos</h1>
        @if ($errors->any()) <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div> @endif
        @include('admin.photos.partials.location-selector')
        <label class="block text-sm font-semibold">Catégorie <select name="photo_category_id" class="mt-1 w-full rounded-md border border-black/15 p-2"><option value="">Aucune</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
        <input type="file" name="files[]" multiple required accept="image/jpeg,image/png,image/webp" class="block w-full rounded-md border border-black/15 p-3">
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-semibold">Titulaire <input name="copyright_holder" value="{{ old('copyright_holder', config('fotometro.photos.default_copyright_holder')) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
            <label class="block text-sm font-semibold">Licence <select name="license" class="mt-1 w-full rounded-md border border-black/15 p-2">@foreach($licenses as $license)<option value="{{ $license->value }}" @selected($license->value === config('fotometro.photos.default_license'))>{{ $license->label() }}</option>@endforeach</select></label>
        </div>
        <label class="block text-sm font-semibold">Mention copyright <input name="copyright_notice" value="{{ old('copyright_notice', config('fotometro.photos.default_copyright_notice')) }}" class="mt-1 w-full rounded-md border border-black/15 p-2"></label>
        <fieldset class="rounded-md border border-black/10 p-4">
            <legend class="px-1 text-sm font-semibold">Publication</legend>
            <label class="mt-2 flex items-start gap-2 text-sm font-semibold">
                <input type="radio" name="publish_mode" value="auto" checked>
                <span>Publier automatiquement une fois les photos prêtes <span class="block font-normal text-black/60">Les photos seront visibles sur la fiche de la station dès que leur traitement sera terminé.</span></span>
            </label>
            <label class="mt-3 flex items-center gap-2 text-sm font-semibold">
                <input type="radio" name="publish_mode" value="draft">
                Garder en brouillon
            </label>
        </fieldset>
        <p class="text-sm text-black/60">Limite : {{ config('fotometro.photos.batch_limit') }} fichiers, {{ config('fotometro.photos.max_upload_mb') }} Mo par fichier. Traitement synchrone : {{ config('fotometro.photos.process_synchronously') ? 'oui' : 'non' }}.</p>
        <button class="rounded-md bg-black px-4 py-2 font-semibold text-white">Importer</button>
    </form>
</x-layouts.app>
