<x-layouts.auth title="Compte en attente - fotométro" :showcase-photo="$showcasePhoto">
    <h1 class="text-2xl font-semibold">Compte créé</h1>
    <p class="mt-3 text-black/70">
        Votre compte a bien été créé. Il est actuellement en attente de validation par un administrateur — vous recevrez un email dès qu'il sera approuvé, et vous pourrez alors vous connecter.
    </p>

    <a href="{{ route('home') }}" class="mt-6 inline-block font-semibold text-black underline">Retour à l'accueil</a>
</x-layouts.auth>
