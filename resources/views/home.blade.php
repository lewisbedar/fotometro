<x-layouts.app title="fotometro">
    <section class="grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-start">
        <div class="space-y-7">
            <div class="space-y-4">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Métro parisien</p>
                <h1 class="max-w-2xl text-5xl font-semibold tracking-normal text-[#151515] sm:text-6xl">fotometro</h1>
                <p class="max-w-2xl text-xl leading-8 text-black/70">
                    Catalogue photographique des stations du métro parisien
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                    <p class="text-sm text-black/55">Stations en base</p>
                    <p class="mt-2 text-4xl font-semibold">{{ $stationCount }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
                    <p class="text-sm text-black/55">Lignes de démonstration</p>
                    <p class="mt-2 text-4xl font-semibold">{{ $lines->count() }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-black/5">
            <h2 class="text-lg font-semibold">Lignes disponibles</h2>
            <div class="mt-5 space-y-3">
                @forelse ($lines as $line)
                    <div class="flex items-center justify-between gap-4 rounded-md border border-black/10 p-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-full text-sm font-bold" style="background-color: {{ $line->color }}; color: {{ $line->text_color }}">
                                {{ $line->code }}
                            </span>
                            <div>
                                <p class="font-semibold">{{ $line->name }}</p>
                                <p class="text-sm text-black/55">{{ $line->stations_count }} station(s)</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-black/60">Aucune ligne n'est encore référencée.</p>
                @endforelse
            </div>
        </div>
    </section>
</x-layouts.app>
