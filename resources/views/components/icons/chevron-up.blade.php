@props(['title' => null])

<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 -960 960 960" fill="none" aria-hidden="{{ $title ? 'false' : 'true' }}" role="{{ $title ? 'img' : 'presentation' }}">
    @if ($title)
        <title>{{ $title }}</title>
    @endif
    <path d="M480-554 283-357l-43-43 240-240 240 240-43 43-197-197Z" fill="currentColor"/>
</svg>
