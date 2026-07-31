<x-layouts.app title="Merci - fotométro" :full-width="false">
    <div class="mx-auto max-w-md rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
        <h1 class="text-2xl font-semibold">Merci !</h1>
        <p class="mt-3 text-black/70">
            Votre photo a bien été envoyée. Elle sera examinée par un modérateur avant d'apparaître sur la fiche de la station.
        </p>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('photos.upload.create') }}" class="rounded-md bg-black px-4 py-2 font-semibold text-white">Ajouter une autre photo</a>
            <a href="{{ route('home') }}" class="font-semibold text-black underline">Retour à la carte</a>
        </div>
    </div>
</x-layouts.app>
