<x-layouts.app
    :title="$profileUser->name.' - fotométro'"
    :description="'Découvrez les photographies publiées par '.$profileUser->name.' sur fotométro.'"
    :canonical="route('profiles.show', $profileUser)"
    :full-width="true"
>
    <div class="space-y-6">
        @if (session('status'))
            <p class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</p>
        @endif

        <header class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5" x-data="{ editingProfile: false }">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                @if ($profileUser->avatar_url)
                    <img src="{{ $profileUser->avatar_url }}" alt="Photo de profil de {{ $profileUser->name }}" class="h-32 w-32 flex-none rounded-full object-cover ring-1 ring-black/10">
                @else
                    <span class="grid h-32 w-32 flex-none place-items-center rounded-full bg-black/5 text-4xl font-semibold text-black/40 ring-1 ring-black/10">
                        {{ Str::of($profileUser->name)->substr(0, 1)->upper() }}
                    </span>
                @endif

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 class="text-2xl font-semibold">{{ $profileUser->name }}</h1>
                            <p class="text-sm text-black/55">{{ '@'.$profileUser->username }}</p>
                        </div>

                        @if ($isOwnProfile)
                            <button
                                type="button"
                                x-on:click="editingProfile = ! editingProfile"
                                class="inline-flex flex-none items-center gap-1.5 rounded-full border border-black/15 px-3 py-1.5 text-sm font-semibold hover:bg-black hover:text-white"
                            >
                                <x-icons.edit class="h-4 w-4" /> Modifier le profil
                            </button>
                        @endif
                    </div>

                    <p class="mt-3 text-sm text-black/70">{{ $profileUser->bio ?: 'Aucune bio pour l’instant.' }}</p>

                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        @if ($profileUser->favoriteLine)
                            <a href="{{ route('lines.show', $profileUser->favoriteLine) }}" class="rounded-full px-3 py-1 font-bold" style="background: {{ $profileUser->favoriteLine->color }}; color: {{ $profileUser->favoriteLine->text_color }}">
                                Ligne favorite : {{ $profileUser->favoriteLine->code }}
                            </a>
                        @endif
                        @if ($profileUser->favoriteStation)
                            <a href="{{ route('stations.show', $profileUser->favoriteStation) }}" class="rounded-full bg-black/5 px-3 py-1 font-semibold hover:bg-black hover:text-white">
                                Station favorite : {{ $profileUser->favoriteStation->name }}
                            </a>
                        @endif
                        <span class="rounded-full bg-black/5 px-3 py-1 font-semibold text-black/60">Membre depuis {{ $profileUser->created_at->format('m/Y') }}</span>
                    </div>

                    @if ($isOwnProfile)
                        <div x-show="editingProfile" x-cloak class="mt-5 grid min-w-0 gap-5 border-t border-black/10 pt-5 sm:grid-cols-2">
                            <div class="min-w-0">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-black/40">Photo de profil</p>
                                <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" class="space-y-2">
                                    @csrf
                                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required class="w-full max-w-full text-xs">
                                    @error('avatar')
                                        <p class="text-xs text-red-700">{{ $message }}</p>
                                    @enderror
                                    <div class="flex gap-2">
                                        <button class="rounded-md bg-[#151515] px-3 py-1.5 text-xs font-semibold text-white hover:bg-black">Envoyer</button>
                                        @if ($profileUser->avatar_path)
                                            <button
                                                type="submit"
                                                form="avatar-destroy-form"
                                                class="rounded-md border border-black/15 px-3 py-1.5 text-xs font-semibold hover:bg-black hover:text-white"
                                            >
                                                Supprimer
                                            </button>
                                        @endif
                                    </div>
                                </form>
                                @if ($profileUser->avatar_path)
                                    <form id="avatar-destroy-form" method="POST" action="{{ route('profile.avatar.destroy') }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endif
                            </div>

                            <div class="min-w-0">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-black/40">Bio</p>
                                <form method="POST" action="{{ route('profile.bio.update') }}" class="space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    <textarea name="bio" rows="3" maxlength="1000" class="w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-black">{{ old('bio', $profileUser->bio) }}</textarea>
                                    @error('bio')
                                        <p class="text-xs text-red-700">{{ $message }}</p>
                                    @enderror
                                    <button class="rounded-md bg-[#151515] px-3 py-1.5 text-xs font-semibold text-white hover:bg-black">Enregistrer</button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 gap-6 {{ $badges->isNotEmpty() ? 'sm:grid-cols-3' : '' }}">
            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5 {{ $badges->isNotEmpty() ? 'sm:col-span-2' : '' }}">
                <div class="grid grid-cols-3 divide-x divide-black/10">
                    <div class="px-2 text-center">
                        <p class="text-2xl font-bold text-black">{{ $publishedPhotoCount }}</p>
                        <p class="mt-0.5 text-xs text-black/50">Photo(s) publiée(s)</p>
                    </div>
                    <div class="px-2 text-center">
                        <p class="text-2xl font-bold text-black">{{ $stationCount }}</p>
                        <p class="mt-0.5 text-xs text-black/50">Station(s) couverte(s)</p>
                    </div>
                    <div class="px-2 text-center">
                        <p class="text-2xl font-bold text-black">{{ $lineCount }}</p>
                        <p class="mt-0.5 text-xs text-black/50">Ligne(s) couverte(s)</p>
                    </div>
                </div>
            </div>

            @if ($badges->isNotEmpty())
                <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-black/5">
                    <h2 class="text-sm font-semibold">Badges</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($badges as $badge)
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                                style="background: {{ $badge->displayBackground() }}; color: {{ $badge->displayTextColor() }}"
                                title="{{ $badge->description }}"
                            >
                                {{ $badge->label }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <livewire:profile-gallery :user="$profileUser" :key="'profile-gallery-'.$profileUser->id" />
    </div>
</x-layouts.app>
