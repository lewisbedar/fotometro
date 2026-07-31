@props([
    'title' => 'fotométro',
    'showcasePhoto' => null,
])

<x-layouts.app :title="$title" :fullscreen="true">
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="flex items-center justify-center px-6 py-10 sm:px-10">
            <div class="w-full max-w-sm">
                <x-fotometro-logo class="mb-8" />

                {{ $slot }}
            </div>
        </div>

        <div class="relative hidden overflow-hidden bg-[#12326b] lg:block">
            @if ($showcasePhoto && $showcasePhoto->web_url)
                <img src="{{ $showcasePhoto->web_url }}" alt="" class="h-full w-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/5 to-transparent"></div>

                <div class="absolute bottom-6 right-6 flex flex-col items-end gap-2">
                    <span class="ratp-sign-mini">
                        <span class="ratp-sign-mini-plate">
                            <span class="ratp-sign-mini-text">{{ $showcasePhoto->station->name }}</span>
                        </span>
                    </span>
                    @if ($showcasePhoto->station->lines->isNotEmpty())
                        <div class="flex flex-wrap justify-end gap-1">
                            @foreach ($showcasePhoto->station->lines as $line)
                                <span class="grid h-5 min-w-5 place-items-center rounded-full px-1 text-[11px] font-bold" style="background: {{ $line->color }}; color: {{ $line->text_color }}">{{ $line->code }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if ($showcasePhoto->copyright_notice)
                        <p class="text-xs text-white/80">{{ $showcasePhoto->copyright_notice }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
