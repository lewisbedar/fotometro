<x-layouts.app title="Importer des photos - fotométro">
    <div
        class="space-y-5"
        x-data="fotometroPhotoImportWizard({{
            \Illuminate\Support\Js::from([
                'lineStationsUrl' => route('admin.api.lines.stations', ['line' => '__LINE__']),
                'stationAccessesUrl' => route('admin.api.stations.accesses', ['station' => '__STATION__']),
                'detectStationUrl' => route('admin.api.photos.detect-station'),
            ])
        }})"
    >
        <h1 class="text-2xl font-semibold">Importer des photos</h1>
        @if ($errors->any()) <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div> @endif

        <section
            x-show="step === 'drop'"
            class="space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5"
            x-on:dragover.prevent
            x-on:drop.prevent="filesAdded($event.dataTransfer.files)"
        >
            <div class="rounded-lg border-2 border-dashed border-black/15 p-10 text-center">
                @if (file_exists(public_path('images/upload_banner.png')))
                    <img src="{{ asset('images/upload_banner.png') }}" alt="" class="mx-auto mb-4 h-32 w-auto object-contain">
                @endif
                <p class="font-semibold">Faites glisser des photos ici, ou</p>
                <button type="button" class="mt-3 rounded-md bg-black px-4 py-2 font-semibold text-white" x-on:click="$refs.browseInput.click()">Parcourir</button>
                <input
                    x-ref="browseInput"
                    type="file"
                    multiple
                    required
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    x-on:change="filesAdded($event.target.files); $event.target.value = ''"
                >
                <p class="mt-3 text-sm text-black/60">Limite : {{ config('fotometro.photos.batch_limit') }} fichiers, {{ config('fotometro.photos.max_upload_mb') }} Mo par fichier.</p>
            </div>

            <div>
                <h2 class="text-base font-semibold">Avant d’importer</h2>
                <div class="mt-2 flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm font-semibold text-amber-900">
                    <x-icons.attribution class="h-5 w-5 shrink-0" />
                    <p>Vous devez être l’auteur ou l’autrice de la photo importée : c’est vous qui en détenez les droits, et elle ne doit pas avoir été générée par une IA.</p>
                </div>
                <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="flex items-start gap-2 text-sm text-black/70">
                        <x-icons.crop class="h-5 w-5 shrink-0 text-black/50" />
                        <p>Photo bien cadrée et nette</p>
                    </div>
                    <div class="flex items-start gap-2 text-sm text-black/70">
                        <x-icons.brightness class="h-5 w-5 shrink-0 text-black/50" />
                        <p>Bonne visibilité du sujet (pas de contre-jour extrême)</p>
                    </div>
                    <div class="flex items-start gap-2 text-sm text-black/70">
                        <x-icons.privacy class="h-5 w-5 shrink-0 text-black/50" />
                        <p>Évitez les personnes reconnaissables ; floutez le visage si une personne apparaît nettement</p>
                    </div>
                    <div class="flex items-start gap-2 text-sm text-black/70">
                        <x-icons.focus class="h-5 w-5 shrink-0 text-black/50" />
                        <p>Un sujet clair par photo (accès, quai, signalétique, etc.)</p>
                    </div>
                </div>
            </div>
        </section>

        <form
            x-show="step === 'review'"
            method="POST"
            action="{{ route('photos.upload.store') }}"
            enctype="multipart/form-data"
            class="space-y-5"
            x-on:submit="handleSubmit($event)"
        >
            @csrf
            <input x-ref="filesInput" type="file" name="files[]" class="hidden">

            <p x-show="submitError" x-text="submitError" class="rounded-md bg-red-50 p-3 text-sm text-red-800"></p>

            <div class="grid gap-4 lg:grid-cols-[96px_1fr]">
                <div class="flex flex-row gap-2 overflow-x-auto lg:flex-col lg:self-start lg:overflow-visible lg:sticky lg:top-6">
                    <button type="button" class="flex aspect-square w-16 shrink-0 items-center justify-center rounded-lg border border-dashed border-black/25 bg-white text-black/50 lg:w-full" x-on:click="$refs.browseInput.click()" aria-label="Ajouter des photos"><x-icons.add class="h-5 w-5" /></button>
                    <template x-for="photo in photos" :key="photo.id">
                        <button type="button" class="aspect-square w-16 shrink-0 overflow-hidden rounded-lg ring-1 ring-black/10 lg:w-full" x-on:click="scrollToPhoto(photo.id)">
                            <img :src="photo.previewUrl" class="h-full w-full object-cover" alt="">
                        </button>
                    </template>
                </div>

                <div class="space-y-4">
                    <template x-for="(photo, index) in photos" :key="photo.id">
                        <div :id="`photo-row-${photo.id}`" class="flex flex-col gap-4 rounded-lg border border-black/10 bg-black/[0.02] p-4 md:flex-row">
                            <img :src="photo.previewUrl" class="h-48 w-full shrink-0 self-stretch rounded-md object-cover md:h-auto md:w-64" alt="">

                            <div class="flex-1 space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs text-black/55" x-text="photo.detectionStatus"></p>
                                    <button type="button" class="shrink-0 text-black/60 hover:text-black" x-on:click="removePhoto(index)" aria-label="Retirer cette photo"><x-icons.trash class="h-4 w-4" /></button>
                                </div>

                                <div class="grid gap-3 lg:grid-cols-[1fr_1fr_1fr_auto]">
                                    <label class="block text-sm font-semibold">Ligne
                                        <select class="mt-1 w-full rounded-md border border-black/15 bg-white p-2" x-model="photo.lineId" x-on:change="lineChangedFor(photo)">
                                            <option value="">Sélectionner une ligne</option>
                                            @foreach($lines as $line)
                                                <option value="{{ $line->id }}" style="background-color: {{ $line->color }}22;">Ligne {{ $line->code }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block text-sm font-semibold">Station
                                        <select
                                            class="mt-1 w-full rounded-md border border-black/15 bg-white p-2 disabled:bg-black/5 disabled:text-black/45"
                                            x-model="photo.stationId"
                                            x-on:change="stationChangedFor(photo)"
                                            x-bind:disabled="! photo.lineId || photo.loadingStations"
                                        >
                                            <option value="" x-text="photo.loadingStations ? 'Chargement...' : 'Sélectionner une station'"></option>
                                            <template x-for="station in photo.stations" :key="station.id">
                                                <option :value="station.id" x-text="station.name"></option>
                                            </template>
                                        </select>
                                    </label>
                                    <label class="block text-sm font-semibold">Accès optionnel
                                        <select
                                            class="mt-1 w-full rounded-md border border-black/15 bg-white p-2 disabled:bg-black/5 disabled:text-black/45"
                                            x-model="photo.accessId"
                                            x-bind:disabled="! photo.stationId || photo.loadingAccesses"
                                        >
                                            <option value="" x-text="photo.loadingAccesses ? 'Chargement...' : 'Aucun accès'"></option>
                                            <template x-for="access in photo.accesses" :key="access.id">
                                                <option :value="access.id" x-text="access.number ? `N°${access.number} — ${access.name}` : access.name"></option>
                                            </template>
                                        </select>
                                    </label>
                                    <button type="button" title="Appliquer cette localisation à toutes les photos" class="mt-6 flex items-center justify-center rounded-md border border-black/15 bg-white px-2" x-on:click="duplicateLocation(index)"><x-icons.duplicate class="h-4 w-4" /></button>

                                    <input type="hidden" :name="`photos[${index}][station_id]`" :value="photo.stationId">
                                    <input type="hidden" :name="`photos[${index}][station_access_id]`" :value="photo.accessId">
                                    <input type="hidden" :name="`photos[${index}][line_id]`" :value="photo.coversWholeStation ? '' : photo.lineId">
                                </div>

                                <div class="flex items-start gap-2 rounded-md border border-black/10 bg-white p-3" x-show="stationHasMultipleLinesFor(photo)" x-cloak>
                                    <label class="flex flex-1 items-start gap-2 text-sm font-semibold">
                                        <input type="checkbox" class="mt-0.5" x-model="photo.coversWholeStation">
                                        <span>Cette photo concerne toute la station, pas seulement la ligne choisie</span>
                                    </label>
                                    <div class="group relative">
                                        <button type="button" class="text-black/50 hover:text-black" aria-label="Aide">
                                            <x-icons.help class="h-5 w-5" />
                                        </button>
                                        <div
                                            class="pointer-events-none absolute right-0 z-10 mt-2 w-72 rounded-md border border-black/10 bg-white p-3 text-sm text-black/70 opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-within:opacity-100"
                                        >
                                            Certaines stations desservent plusieurs lignes (correspondances). Cochez cette case si la photo montre un élément commun à toute la station — une entrée, un couloir de correspondance, un totem — plutôt qu’un élément propre à une seule ligne.
                                            <p class="mt-2 text-black/55">Exemple : l’entrée principale de Champs-Élysées - Clemenceau (lignes 1 et 13) concerne toute la station.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-3 lg:grid-cols-[1fr_auto]">
                                    <div class="block text-sm font-semibold" x-data="{ open: false }">
                                        Catégories
                                        <div class="relative mt-1">
                                            <button
                                                type="button"
                                                class="flex w-full items-center justify-between rounded-md border border-black/15 bg-white p-2 text-left font-normal"
                                                x-on:click="open = ! open"
                                            >
                                                <span x-text="photo.categoryIds.length ? photo.categoryIds.length + ' sélectionnée(s)' : 'Aucune'"></span>
                                                <x-icons.chevron-down class="h-4 w-4" />
                                            </button>
                                            <div
                                                class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-black/15 bg-white p-2 shadow-lg"
                                                x-show="open"
                                                x-cloak
                                                x-on:click.outside="open = false"
                                            >
                                                <x-category-checklist :categories="$categories" alpine-model="photo.categoryIds" />
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" title="Appliquer ces catégories à toutes les photos" class="mt-6 flex items-center justify-center rounded-md border border-black/15 bg-white px-2" x-on:click="duplicateField(index, 'categoryIds')"><x-icons.duplicate class="h-4 w-4" /></button>
                                    <template x-for="categoryId in photo.categoryIds" :key="categoryId">
                                        <input type="hidden" :name="`photos[${index}][photo_category_ids][]`" :value="categoryId">
                                    </template>
                                </div>

                                <div class="grid gap-3 lg:grid-cols-[1fr_auto]">
                                    <label class="block text-sm font-semibold">Description
                                        <input class="mt-1 w-full rounded-md border border-black/15 p-2" x-model="photo.description">
                                    </label>
                                    <button type="button" title="Appliquer cette description à toutes les photos" class="mt-6 flex items-center justify-center rounded-md border border-black/15 bg-white px-2" x-on:click="duplicateField(index, 'description')"><x-icons.duplicate class="h-4 w-4" /></button>
                                    <input type="hidden" :name="`photos[${index}][description]`" :value="photo.description">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="text-base font-semibold">Réglages du lot</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm font-semibold">Titulaire</p>
                        <p class="mt-1 rounded-md border border-black/15 bg-black/5 p-2 text-sm text-black/70">{{ auth()->user()->name }}</p>
                    </div>
                    <label class="block text-sm font-semibold">Licence <select name="license" class="mt-1 w-full rounded-md border border-black/15 p-2">@foreach($licenses as $license)<option value="{{ $license->value }}" @selected($license->value === config('fotometro.photos.default_license'))>{{ $license->label() }}</option>@endforeach</select></label>
                </div>
                <p class="text-xs text-black/55">La mention de copyright est générée automatiquement à partir du titulaire et de la licence choisie.</p>
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
                <button class="rounded-md bg-black px-4 py-2 font-semibold text-white">Importer</button>
            </div>
        </form>
    </div>
</x-layouts.app>
