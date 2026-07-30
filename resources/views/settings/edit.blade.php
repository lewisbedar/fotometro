<x-layouts.app title="Paramètres du compte - fotométro">
    <div class="mx-auto max-w-xl space-y-6">
        <a href="{{ route('profiles.show', $settingsUser) }}" class="block text-right text-sm font-medium text-black/65 hover:text-black hover:underline">Retour à mon profil</a>

        @if (session('status'))
            <p class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</p>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
            <h1 class="text-2xl font-semibold">Paramètres du compte</h1>

            <form method="POST" action="{{ route('settings.update') }}" class="mt-6 space-y-5">
                @csrf
                @method('PATCH')

                <div>
                    <label for="username" class="block text-sm font-medium">Nom d'utilisateur</label>
                    <input id="username" name="username" type="text" value="{{ old('username', $settingsUser->username) }}" required
                        class="mt-2 w-full rounded-md border border-black/15 bg-white px-3 py-2 outline-none focus:border-black">
                    <p class="mt-1 text-xs text-black/50">Lettres minuscules, chiffres et tirets uniquement.</p>
                    @error('username')
                        <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $settingsUser->email) }}" required
                        class="mt-2 w-full rounded-md border border-black/15 bg-white px-3 py-2 outline-none focus:border-black">
                    @error('email')
                        <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="copyright_display_name" class="block text-sm font-medium">Nom affiché pour le copyright</label>
                    <input id="copyright_display_name" name="copyright_display_name" type="text" value="{{ old('copyright_display_name', $settingsUser->copyright_display_name) }}"
                        class="mt-2 w-full rounded-md border border-black/15 bg-white px-3 py-2 outline-none focus:border-black">
                    <p class="mt-1 text-xs text-black/50">Utilisé sur vos prochaines photos publiées. Laissez vide pour utiliser « {{ $settingsUser->name }} ».</p>
                    @error('copyright_display_name')
                        <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <button class="w-full rounded-md bg-[#151515] px-4 py-2.5 font-semibold text-white hover:bg-black">
                    Enregistrer
                </button>
            </form>
        </div>

        <div class="rounded-lg border border-red-200 bg-red-50 p-6">
            <h2 class="text-lg font-semibold text-red-900">Clôturer mon compte</h2>
            <p class="mt-2 text-sm text-red-800">Cette action est définitive. Vos photos déjà publiées resteront visibles mais ne seront plus rattachées à votre compte.</p>

            <form method="POST" action="{{ route('settings.destroy') }}" class="mt-4 space-y-3">
                @csrf
                @method('DELETE')
                <div>
                    <label for="password" class="block text-sm font-medium text-red-900">Mot de passe</label>
                    <input id="password" name="password" type="password" required
                        class="mt-2 w-full rounded-md border border-red-300 bg-white px-3 py-2 outline-none focus:border-red-600">
                    @error('password')
                        <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <button class="w-full rounded-md bg-red-700 px-4 py-2.5 font-semibold text-white hover:bg-red-800">
                    Clôturer définitivement mon compte
                </button>
            </form>
        </div>
    </div>
</x-layouts.app>
