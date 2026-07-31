<x-layouts.app title="Modifier {{ $editUser->name }} - fotométro" :full-width="true">
    <form method="POST" action="{{ route('admin.users.update', $editUser) }}" class="mx-auto max-w-2xl space-y-5 rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
        @csrf
        @method('PATCH')
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Administration</p>
            <h1 class="mt-2 text-2xl font-semibold">Modifier {{ $editUser->name }}</h1>
        </div>
        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif
        <label class="block text-sm font-semibold">Nom
            <input name="name" value="{{ old('name', $editUser->name) }}" class="mt-1 w-full rounded-md border border-black/15 p-2">
        </label>
        <label class="block text-sm font-semibold">Nom d'utilisateur
            <input name="username" value="{{ old('username', $editUser->username) }}" class="mt-1 w-full rounded-md border border-black/15 p-2">
        </label>
        <label class="block text-sm font-semibold">Email
            <input name="email" type="email" value="{{ old('email', $editUser->email) }}" class="mt-1 w-full rounded-md border border-black/15 p-2">
        </label>
        <label class="block text-sm font-semibold">Rôle
            <select name="role" class="mt-1 w-full rounded-md border border-black/15 p-2">
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $editUser->role->value) === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex gap-2">
            <button class="rounded-md bg-black px-4 py-2 font-semibold text-white">Enregistrer</button>
            <a href="{{ route('admin.users.index', ['status' => $editUser->status->value]) }}" class="rounded-md border border-black/10 px-4 py-2 font-semibold hover:bg-black hover:text-white">Annuler</a>
        </div>
    </form>
</x-layouts.app>
