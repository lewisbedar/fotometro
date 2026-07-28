<div class="map-about-backdrop" x-show="isAboutOpen" x-transition.opacity x-cloak x-on:click.self="closeAbout()" role="presentation">
    <section
        class="map-about-modal map-glass"
        role="dialog"
        aria-modal="true"
        aria-labelledby="map-about-title"
        x-on:click.stop
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">À propos</p>
                <h2 id="map-about-title" class="mt-1 text-2xl font-semibold">fotométro</h2>
            </div>
            <button type="button" class="map-icon-button" x-ref="aboutClose" x-on:click="closeAbout()" aria-label="Fermer la fenêtre À propos">×</button>
        </div>

        <div class="mt-5 space-y-4 text-sm leading-6 text-black/70">
            <p>fotométro est un projet photographique consacré aux stations du métro parisien. Il permet d’explorer le réseau, de découvrir les stations et de documenter leurs quais, accès, architecture, signalétique et détails.</p>
            <p>Les photographies présentées sur fotométro sont protégées par le droit d’auteur. Toute reproduction ou réutilisation est soumise aux conditions indiquées sur chaque photographie.</p>

            <div class="grid gap-3 sm:grid-cols-2">
                <section class="rounded-md bg-white/70 p-3 ring-1 ring-black/10">
                    <h3 class="font-semibold">À propos du projet</h3>
                    <p class="mt-1 text-black/60">Catalogue photographique public du métro parisien.</p>
                </section>
                <section class="rounded-md bg-white/70 p-3 ring-1 ring-black/10">
                    <h3 class="font-semibold">Crédits</h3>
                    <p class="mt-1 text-black/60">Photographies selon les mentions affichées sur chaque page photo.</p>
                </section>
                <section class="rounded-md bg-white/70 p-3 ring-1 ring-black/10">
                    <h3 class="font-semibold">Sources des données</h3>
                    <p class="mt-1 text-black/60">Île-de-France Mobilités pour les données du réseau et des accès. OpenStreetMap contributors pour le fond cartographique.</p>
                </section>
                <section class="rounded-md bg-white/70 p-3 ring-1 ring-black/10">
                    <h3 class="font-semibold">Mentions légales</h3>
                    <p class="mt-1 text-black/60">Fotométro n’est pas affilié à la RATP ou à Île-de-France Mobilités.</p>
                </section>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black/10 pt-4">
                <p class="text-black/60">Idée et conception : <span class="font-semibold text-black/80">Lewis Bedar</span></p>
                <div class="flex items-center gap-3">
                    <a href="https://lewis.bedar.fr" target="_blank" rel="noopener noreferrer" class="opacity-80 transition hover:opacity-100" aria-label="Site de Lewis Bedar">
                        @if (file_exists(public_path('images/logo-lewis-bedar.png')))
                            <img src="{{ asset('images/logo-lewis-bedar.png') }}" alt="Lewis Bedar" class="h-10 w-auto">
                        @else
                            <span class="text-sm font-semibold underline">lewis.bedar.fr</span>
                        @endif
                    </a>
                    <a href="https://flux-croises.fr" target="_blank" rel="noopener noreferrer" class="opacity-80 transition hover:opacity-100" aria-label="Site de l’association Flux Croisés">
                        @if (file_exists(public_path('images/logo-flux-croises.png')))
                            <img src="{{ asset('images/logo-flux-croises.png') }}" alt="Flux Croisés" class="h-5 w-auto">
                        @else
                            <span class="text-sm font-semibold underline">flux-croises.fr</span>
                        @endif
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
