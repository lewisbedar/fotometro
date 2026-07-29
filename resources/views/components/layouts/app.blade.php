@props([
    'title' => 'fotométro',
    'description' => null,
    'canonical' => null,
    'fullWidth' => true,
    'fullscreen' => false,
])

<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }}</title>
        @if (file_exists(public_path('images/favicon.png')))
            <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
        @endif
        @if (file_exists(public_path('images/favicon-touch.png')))
            <link rel="apple-touch-icon" href="{{ asset('images/favicon-touch.png') }}">
        @endif
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
        @if ($fullscreen)
            {{ $slot }}
        @else
            <div @class([
                'mx-auto flex min-h-screen w-full flex-col px-5 py-6 sm:px-8',
                'max-w-[1600px]' => $fullWidth,
                'max-w-6xl' => ! $fullWidth,
            ])>
                <header class="flex flex-wrap items-center justify-between gap-4 border-b border-black/10 pb-5">
                    <x-fotometro-logo />
                    <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                        @auth
                            <nav class="flex flex-wrap items-center gap-1 rounded-full bg-black/5 p-1 text-xs sm:text-sm">
                                <a href="{{ route('admin.dashboard') }}" class="rounded-full px-3 py-1.5 font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-black text-white' : 'text-[#151515] hover:bg-black/10' }}">Tableau de bord</a>
                                <a href="{{ route('admin.photos.index') }}" class="rounded-full px-3 py-1.5 font-medium transition {{ request()->routeIs('admin.photos.*') ? 'bg-black text-white' : 'text-[#151515] hover:bg-black/10' }}">Photos</a>
                                <a href="{{ route('admin.photo-categories.index') }}" class="rounded-full px-3 py-1.5 font-medium transition {{ request()->routeIs('admin.photo-categories.*') ? 'bg-black text-white' : 'text-[#151515] hover:bg-black/10' }}">Catégories</a>
                            </nav>
                            <a href="{{ route('home') }}" class="text-xs font-medium text-[#151515] hover:underline sm:text-sm">Retour à la carte</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="rounded-md border border-black/15 bg-white px-3 py-2 text-xs font-medium hover:bg-black hover:text-white sm:text-sm">
                                    Déconnexion
                                </button>
                            </form>
                        @else
                            <a href="{{ route('home') }}" class="text-xs font-medium text-[#151515] hover:underline sm:text-sm">Retour à la carte</a>
                            <a href="{{ route('login') }}" class="rounded-md border border-black/15 bg-white px-3 py-2 text-xs font-medium hover:bg-black hover:text-white sm:text-sm">
                                Connexion admin
                            </a>
                        @endauth
                    </div>
                </header>

                <main class="flex-1 py-10">
                    {{ $slot }}
                </main>
            </div>
        @endif

        @livewireScripts
    </body>
</html>
