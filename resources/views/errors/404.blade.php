<x-layouts.app title="Page introuvable - fotométro">
    <div class="mx-auto flex max-w-lg flex-col items-center py-10 text-center">
        <img src="{{ asset('images/metro_bug.png') }}" alt="" class="h-48 w-48 object-contain">

        <p class="mt-6 text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Erreur 404</p>
        <h1 class="mt-2 text-2xl font-semibold">Cette page n’existe pas</h1>
        <p class="mt-3 text-black/70">
            La page que vous cherchez a peut-être été déplacée, supprimée, ou n’a jamais existé — un peu comme une station fantôme du métro.
        </p>

        <div class="mt-6 flex flex-wrap justify-center gap-3">
            <a href="{{ route('home') }}" class="rounded-md bg-black px-4 py-2.5 font-semibold text-white hover:bg-black/85">Retour à la carte</a>
        </div>
    </div>
</x-layouts.app>
