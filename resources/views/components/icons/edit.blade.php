@props(['title' => null])

<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 -960 960 960" fill="none" aria-hidden="{{ $title ? 'false' : 'true' }}" role="{{ $title ? 'img' : 'presentation' }}">
    @if ($title)
        <title>{{ $title }}</title>
    @endif
    <path d="M180-180h44l472-471-44-44-472 471v44Zm-60 60v-128l575-574q8-8 19-12.5t23-4.5q11 0 22 4.5t20 12.5l44 44q9 9 13 20t4 22q0 11-4.5 22.5T823-694L248-120H120Zm659-617-41-41 41 41Zm-105 64-22-22 44 44-22-22Z" fill="currentColor"/>
</svg>
