@props(['title' => null])

<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none" aria-hidden="{{ $title ? 'false' : 'true' }}" role="{{ $title ? 'img' : 'presentation' }}">
    @if ($title)
        <title>{{ $title }}</title>
    @endif
    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
    <path d="M6.9 16V8h2.2l2.9 4.2L14.9 8h2.2v8h-2.2v-4.4l-2.2 3.1h-1.4l-2.2-3.1V16H6.9Z" fill="currentColor"/>
</svg>
