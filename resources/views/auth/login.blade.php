<x-layouts.app title="Connexion - fotométro">
    <div class="mx-auto max-w-md rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
        <h1 class="text-2xl font-semibold">Connexion</h1>
        <p class="mt-2 text-sm text-black/60">Connectez-vous à votre compte fotométro.</p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                    class="mt-2 w-full rounded-md border border-black/15 bg-white px-3 py-2 outline-none focus:border-black">
                @error('email')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">Mot de passe</label>
                <input id="password" name="password" type="password" required
                    class="mt-2 w-full rounded-md border border-black/15 bg-white px-3 py-2 outline-none focus:border-black">
                @error('password')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-black/70">
                    <input type="checkbox" name="remember" value="1" class="rounded border-black/20">
                    Se souvenir de moi
                </label>
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-black underline">Mot de passe oublié ?</a>
            </div>

            <button class="w-full rounded-md bg-[#151515] px-4 py-2.5 font-semibold text-white hover:bg-black">
                Se connecter
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-black/60">
            Pas encore de compte ? <a href="{{ route('register') }}" class="font-semibold text-black underline">Créer un compte</a>
        </p>
    </div>
</x-layouts.app>
