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
        @if (session('status'))
            <div
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 4000)"
                x-show="show"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed right-4 top-4 z-[100] flex max-w-sm items-start gap-3 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-800 shadow-lg ring-1 ring-green-600/20"
            >
                <span>{{ session('status') }}</span>
                <button type="button" x-on:click="show = false" class="flex-none text-green-700/60 hover:text-green-900">
                    <x-icons.close class="h-4 w-4" />
                </button>
            </div>
        @endif

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
                            @if (auth()->user()->canModerate())
                                <nav class="flex flex-wrap items-center gap-1 rounded-full bg-black/5 p-1 text-xs sm:text-sm">
                                    <a href="{{ route('admin.dashboard') }}" class="rounded-full px-3 py-1.5 font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-black text-white' : 'text-[#151515] hover:bg-black/10' }}">Tableau de bord</a>
                                    <a href="{{ route('admin.moderation.index') }}" class="rounded-full px-3 py-1.5 font-medium transition {{ request()->routeIs('admin.moderation.*') ? 'bg-black text-white' : 'text-[#151515] hover:bg-black/10' }}">Modération</a>
                                    @if (auth()->user()->isAdmin())
                                        <a href="{{ route('admin.photos.index') }}" class="rounded-full px-3 py-1.5 font-medium transition {{ request()->routeIs('admin.photos.*') ? 'bg-black text-white' : 'text-[#151515] hover:bg-black/10' }}">Photos</a>
                                        <a href="{{ route('admin.users.index') }}" class="rounded-full px-3 py-1.5 font-medium transition {{ request()->routeIs('admin.users.*') ? 'bg-black text-white' : 'text-[#151515] hover:bg-black/10' }}">Comptes</a>
                                        <div class="relative" x-data="{ open: false }" x-on:mouseenter="open = true" x-on:mouseleave="open = false">
                                            @php
                                                $onGestionPage = request()->routeIs('admin.photo-categories.*') || request()->routeIs('admin.photo-rejection-reasons.*');
                                            @endphp
                                            <button
                                                type="button"
                                                x-bind:aria-expanded="open.toString()"
                                                class="flex items-center gap-1 rounded-full px-3 py-1.5 font-medium transition {{ $onGestionPage ? 'bg-black text-white' : 'text-[#151515] hover:bg-black/10' }}"
                                            >
                                                Gestion
                                                <x-icons.chevron-down class="h-3.5 w-3.5" />
                                            </button>
                                            <div x-show="open" x-cloak x-transition class="absolute left-0 top-full z-20 w-48 rounded-lg bg-white px-1.5 pb-1.5 pt-2.5 text-left shadow-lg ring-1 ring-black/10">
                                                <a href="{{ route('admin.photo-categories.index') }}" class="block rounded-md px-3 py-2 font-medium {{ request()->routeIs('admin.photo-categories.*') ? 'bg-black text-white' : 'hover:bg-black/5' }}">Catégories</a>
                                                <a href="{{ route('admin.photo-rejection-reasons.index') }}" class="block rounded-md px-3 py-2 font-medium {{ request()->routeIs('admin.photo-rejection-reasons.*') ? 'bg-black text-white' : 'hover:bg-black/5' }}">Motifs de refus</a>
                                            </div>
                                        </div>
                                    @endif
                                </nav>
                            @endif
                            <a href="{{ route('photos.upload.create') }}" class="rounded-md bg-green-700 px-3 py-2 text-xs font-medium text-white hover:bg-green-800 sm:text-sm">Ajouter une photo</a>
                            <div class="relative" x-data="{ open: false }" x-on:mouseenter="open = true" x-on:mouseleave="open = false">
                                <button type="button" x-bind:aria-expanded="open.toString()" title="Mon compte">
                                    @if (auth()->user()->avatar_url)
                                        <img src="{{ auth()->user()->avatar_url }}" alt="Photo de profil de {{ auth()->user()->name }}" class="h-8 w-8 rounded-full object-cover ring-1 ring-black/10">
                                    @else
                                        <span class="grid h-8 w-8 place-items-center rounded-full bg-black/10 text-xs font-semibold text-black/50 ring-1 ring-black/10">
                                            {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                                        </span>
                                    @endif
                                </button>
                                <div x-show="open" x-cloak x-transition class="absolute right-0 top-full z-20 w-48 rounded-lg bg-white px-1.5 pb-1.5 pt-2.5 text-left text-xs shadow-lg ring-1 ring-black/10 sm:text-sm">
                                    <a href="{{ route('profiles.show', auth()->user()) }}" class="block rounded-md px-3 py-2 font-medium {{ request()->routeIs('profiles.show') ? 'bg-black text-white' : 'hover:bg-black/5' }}">Mon profil</a>
                                    <a href="{{ route('settings.edit') }}" class="block rounded-md px-3 py-2 font-medium {{ request()->routeIs('settings.edit') ? 'bg-black text-white' : 'hover:bg-black/5' }}">Paramètres</a>
                                    <div class="my-1 border-t border-black/10"></div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="block w-full rounded-md px-3 py-2 text-left font-medium hover:bg-black/5">Déconnexion</button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="rounded-md border border-black/15 bg-white px-3 py-2 text-xs font-medium hover:bg-black hover:text-white sm:text-sm">
                                Connexion
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
