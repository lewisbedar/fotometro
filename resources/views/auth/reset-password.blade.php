<x-layouts.app title="Réinitialiser le mot de passe - fotométro">
    <div class="mx-auto max-w-md rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
        <h1 class="text-2xl font-semibold">Réinitialiser le mot de passe</h1>

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autofocus
                    class="mt-2 w-full rounded-md border border-black/15 bg-white px-3 py-2 outline-none focus:border-black">
                @error('email')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">Nouveau mot de passe</label>
                <input id="password" name="password" type="password" required
                    class="mt-2 w-full rounded-md border border-black/15 bg-white px-3 py-2 outline-none focus:border-black">
                @error('password')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium">Confirmer le mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                    class="mt-2 w-full rounded-md border border-black/15 bg-white px-3 py-2 outline-none focus:border-black">
            </div>

            <button class="w-full rounded-md bg-[#151515] px-4 py-2.5 font-semibold text-white hover:bg-black">
                Réinitialiser le mot de passe
            </button>
        </form>
    </div>
</x-layouts.app>
