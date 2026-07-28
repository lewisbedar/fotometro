@props([
    'href' => route('home'),
    'size' => 'default',
])

@php
    $logoExists = file_exists(public_path('images/logo_fotometro.png'));
    $heightClass = match ($size) {
        'small', 'compact' => 'h-8',
        'large' => 'h-12',
        default => 'h-10',
    };
    $baseClass = 'inline-flex items-center gap-3 rounded-md text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#12326b] focus-visible:ring-offset-2';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $baseClass]) }}>
@else
    <span {{ $attributes->merge(['class' => $baseClass]) }}>
@endif
        @if ($logoExists)
            <img src="{{ asset('images/logo_fotometro.png') }}" alt="fotométro" @class([$heightClass, 'w-auto max-w-[240px] object-contain'])>
        @else
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#12326b] text-sm font-bold text-white">fm</span>
            <span>
                <span class="block text-xl font-semibold leading-6">fotométro</span>
                <span class="block text-xs text-black/60">Photographier le métro parisien</span>
            </span>
        @endif
@if ($href)
    </a>
@else
    </span>
@endif
