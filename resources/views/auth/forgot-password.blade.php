<x-layouts.app title="Mot de passe oublié - fotométro">
    <div class="mx-auto max-w-md rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
        <h1 class="text-2xl font-semibold">Mot de passe oublié</h1>
        <p class="mt-2 text-sm text-black/60">Indiquez votre email, nous vous enverrons un lien de réinitialisation.</p>

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                    class="mt-2 w-full rounded-md border border-black/15 bg-white px-3 py-2 outline-none focus:border-black">
                @error('email')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <button class="w-full rounded-md bg-[#151515] px-4 py-2.5 font-semibold text-white hover:bg-black">
                Envoyer le lien de réinitialisation
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-black/60">
            <a href="{{ route('login') }}" class="font-semibold text-black underline">Retour à la connexion</a>
        </p>
    </div>
</x-layouts.app>
