<x-layouts.app title="Motifs de refus - fotométro">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p>
                <h1 class="mt-2 text-3xl font-semibold">Motifs de refus</h1>
            </div>
            <a href="{{ route('admin.photo-rejection-reasons.create') }}" class="flex items-center gap-2 rounded-md bg-black px-4 py-2 font-semibold text-white"><x-icons.add class="h-4 w-4" /> Nouveau motif</a>
        </div>
        @if (session('status')) <p class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</p> @endif
        <div class="divide-y divide-black/10 overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5">
            @foreach ($reasons as $reason)
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div>
                        <p class="font-semibold">{{ $reason->label }}</p>
                        <p class="text-sm text-black/55">{{ $reason->is_active ? 'Actif' : 'Inactif' }}</p>
                    </div>
                    <a href="{{ route('admin.photo-rejection-reasons.edit', $reason) }}" title="Modifier" class="rounded-md border border-black/10 p-2 hover:bg-black hover:text-white"><x-icons.edit class="h-4 w-4" /></a>
                </div>
            @endforeach
        </div>
        {{ $reasons->links() }}
    </div>
</x-layouts.app>
