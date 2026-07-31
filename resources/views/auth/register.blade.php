<x-layouts.auth title="Créer un compte - fotométro" :showcase-photo="$showcasePhoto">
    <h1 class="text-2xl font-semibold">Créer un compte</h1>
    <p class="mt-2 text-sm text-black/60">Votre compte sera soumis à l'approbation d'un administrateur avant de pouvoir vous connecter.</p>

    <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-5">
        @csrf

        {{-- Honeypot: invisible to real visitors, bots that fill every field trip it. --}}
        <div class="hidden" aria-hidden="true">
            <label for="website">Site web</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div>
            <label for="name" class="block text-sm font-medium">Nom</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                class="mt-2 w-full rounded-md border border-black/15 bg-white px-3 py-2 outline-none focus:border-black">
            @error('name')
                <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required
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

        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Confirmer le mot de passe</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                class="mt-2 w-full rounded-md border border-black/15 bg-white px-3 py-2 outline-none focus:border-black">
        </div>

        <button class="w-full rounded-md bg-[#151515] px-4 py-2.5 font-semibold text-white hover:bg-black">
            Créer mon compte
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-black/60">
        Déjà un compte ? <a href="{{ route('login') }}" class="font-semibold text-black underline">Se connecter</a>
    </p>
</x-layouts.auth>
