<div class="map-about-backdrop" x-show="isBetaNoticeOpen" x-transition.opacity x-cloak x-on:click.self="closeBetaNotice()" role="presentation">
    <section
        class="map-about-modal map-glass"
        role="dialog"
        aria-modal="true"
        aria-labelledby="map-beta-title"
        x-on:click.stop
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Version bêta</p>
                <h2 id="map-beta-title" class="mt-1 text-2xl font-semibold">Bienvenue sur fotométro !</h2>
            </div>
            <button type="button" class="map-icon-button" x-ref="betaNoticeClose" x-on:click="closeBetaNotice()" aria-label="Fermer la fenêtre de bienvenue">×</button>
        </div>

        <div class="mt-5 space-y-4 text-sm leading-6 text-black/70">
            <p>fotométro est une plateforme conçue pour cataloguer et présenter des photographies des stations du métro parisien.</p>
            <p>Vous avez pris une photo du quai de la ligne 3 direction Pont de Levallois – Bécon à Saint-Lazare et vous souhaitez la partager dans une photothèque ? Eh bien, cette plateforme est faite pour vous !</p>

            <section class="rounded-md bg-white/70 p-3 ring-1 ring-black/10">
                <h3 class="font-semibold">Avis aux bêta-testeurs</h3>
                <p class="mt-1 text-black/60">Ceci est une version bêta : la plateforme est encore en cours de développement. Des bugs ou des anomalies peuvent donc être présents.</p>
                <p class="mt-2 text-black/60">Si vous en repérez, vous pouvez me les signaler à <strong>contact(arobase)lewis.bedar.fr</strong>. Je vous en serai grandement reconnaissant !</p>
                <p class="mt-2 text-black/60">Si vous avez des idées, des suggestions, n’hésitez pas à me les donner.</p>
            </section>

            <section class="rounded-md bg-white/70 p-3 ring-1 ring-black/10">
                <h3 class="font-semibold">Qu’est-ce qui fonctionne actuellement ?</h3>
                <p class="mt-1 text-black/60">La carte interactive et les fiches des stations sont accessibles.</p>
                <p class="mt-2 text-black/60">Vous ne pouvez pas encore créer de compte ni ajouter vos propres photos.</p>
                <p class="mt-2 text-black/60">Pour le moment, seules deux stations disposent de photographies :</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-black/60">
                    <li>Champs-Élysées – Clemenceau (lignes 1 et 13) ;</li>
                    <li>Botzaris (ligne 7bis).</li>
                </ul>
            </section>

            <section class="rounded-md bg-white/70 p-3 ring-1 ring-black/10">
                <h3 class="font-semibold">Et ensuite ?</h3>
                <p class="mt-1 text-black/60">La priorité est maintenant d’améliorer le processus de catalogage des photographies :</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-black/60">
                    <li>détecter automatiquement la station grâce aux données EXIF de la photo ;</li>
                    <li>améliorer le classement et le filtrage des photographies : signalétique, architecture, détails techniques, entrées et sorties, etc.</li>
                </ul>
                <p class="mt-2 text-black/60">Pour l’instant, la plateforme est uniquement pour les stations de métro. J’exclus donc toutes les nombreuses stations de RER et de Transilien.</p>
            </section>

            <button
                type="button"
                class="inline-flex min-h-9 w-full items-center justify-center rounded-md border border-black/15 bg-white px-4 text-sm font-semibold hover:bg-black hover:text-white"
                x-on:click="closeBetaNotice()"
            >
                J’ai compris, c’est parti !
            </button>
        </div>
    </section>
</div>
