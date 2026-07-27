@props([
    'title' => 'fotometro',
    'description' => null,
    'canonical' => null,
    'fullWidth' => false,
])

<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        @if ($canonical)
            <link rel="canonical" href="{{ $canonical }}">
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-[#f6f1e8] text-[#151515] antialiased">
        <div @class([
            'mx-auto flex min-h-screen w-full flex-col px-5 py-6 sm:px-8',
            'max-w-[1600px]' => $fullWidth,
            'max-w-6xl' => ! $fullWidth,
        ])>
            <header class="flex items-center justify-between gap-4 border-b border-black/10 pb-5">
                <a href="{{ route('home') }}" class="text-xl font-semibold tracking-[0.08em] text-[#151515]">fotometro</a>
                <nav class="flex items-center gap-3 text-sm">
                    @auth
                        <a href="{{ route('admin.dashboard') }}" class="font-medium text-[#151515] hover:underline">Administration</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded-md border border-black/15 bg-white px-3 py-2 font-medium hover:bg-black hover:text-white">
                                Déconnexion
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md border border-black/15 bg-white px-3 py-2 font-medium hover:bg-black hover:text-white">
                            Connexion admin
                        </a>
                    @endauth
                </nav>
            </header>

            <main class="flex-1 py-10">
                {{ $slot }}
            </main>
        </div>
        @livewireScripts
    </body>
</html>
