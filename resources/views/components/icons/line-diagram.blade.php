@props(['title' => null])

<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none" aria-hidden="{{ $title ? 'false' : 'true' }}" role="{{ $title ? 'img' : 'presentation' }}">
    @if ($title)
        <title>{{ $title }}</title>
    @endif
    <path d="M3 12h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <circle cx="6" cy="12" r="2.25" fill="currentColor"/>
    <circle cx="12" cy="12" r="2.25" fill="currentColor"/>
    <circle cx="18" cy="12" r="2.25" fill="currentColor"/>
</svg>
