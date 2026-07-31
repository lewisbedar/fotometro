<x-layouts.app title="Comptes utilisateurs - fotométro" :full-width="false">
    <div class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p>
            <h1 class="mt-2 text-3xl font-semibold">Comptes utilisateurs</h1>
        </div>

        @if ($errors->any())
            <p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</p>
        @endif

        <nav class="flex flex-wrap gap-2 text-sm">
            @foreach ($statuses as $status)
                <a
                    href="{{ route('admin.users.index', ['status' => $status->value]) }}"
                    @class(['rounded-full px-3 py-1.5 font-semibold ring-1 ring-black/10', 'bg-black text-white' => $currentStatus === $status, 'bg-black/5' => $currentStatus !== $status])
                >
                    {{ $status->label() }}
                </a>
            @endforeach
        </nav>

        <div class="divide-y divide-black/10 overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5">
            @forelse ($users as $user)
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold">{{ $user->name }}</p>
                            <span class="rounded-full bg-black/5 px-2 py-0.5 text-xs font-semibold text-black/60">{{ $user->role->label() }}</span>
                        </div>
                        <p class="text-sm text-black/55">{{ $user->email }} · {{ '@'.$user->username }} · inscrit le {{ $user->created_at->format('d/m/Y') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($currentStatus->value !== 'approved')
                            <form method="POST" action="{{ route('admin.users.approve', $user) }}">
                                @csrf
                                <button class="rounded-md border border-black/10 px-3 py-2 text-sm font-semibold hover:bg-black hover:text-white">Approuver</button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.reject', $user) }}" x-data="{ open: false }">
                                @csrf
                                <button type="button" x-on:click="open = ! open" class="rounded-md border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-700 hover:text-white">Refuser</button>
                                <div x-show="open" x-cloak class="mt-2 flex items-center gap-2">
                                    <input type="text" name="rejection_reason" placeholder="Motif (optionnel)" class="rounded-md border border-black/15 p-2 text-sm">
                                    <button class="rounded-md bg-red-700 px-3 py-2 text-sm font-semibold text-white">Confirmer</button>
                                </div>
                            </form>
                        @endif
                        <a href="{{ route('admin.users.edit', $user) }}" title="Modifier" class="rounded-md border border-black/10 p-2 hover:bg-black hover:text-white"><x-icons.edit class="h-4 w-4" /></a>
                        <button form="delete-user-{{ $user->id }}" type="submit" title="Supprimer" class="rounded-md bg-red-700 p-2 text-white hover:bg-red-800" onclick="return confirm('Supprimer le compte de {{ $user->name }} ?')"><x-icons.trash class="h-4 w-4" /></button>
                        <form id="delete-user-{{ $user->id }}" method="POST" action="{{ route('admin.users.destroy', $user) }}">@csrf @method('DELETE')</form>
                    </div>
                </div>
            @empty
                <p class="p-4 text-sm text-black/60">Aucun compte dans cette catégorie.</p>
            @endforelse
        </div>
        {{ $users->links() }}
    </div>
</x-layouts.app>
