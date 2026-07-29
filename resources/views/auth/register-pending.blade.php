<x-layouts.app title="Compte en attente - fotométro">
    <div class="mx-auto max-w-md rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
        <h1 class="text-2xl font-semibold">Compte créé</h1>
        <p class="mt-3 text-black/70">
            Votre compte a bien été créé. Il est actuellement en attente de validation par un administrateur — vous recevrez un email dès qu'il sera approuvé, et vous pourrez alors vous connecter.
        </p>

        <a href="{{ route('home') }}" class="mt-6 inline-block font-semibold text-black underline">Retour à l'accueil</a>
    </div>
</x-layouts.app>
