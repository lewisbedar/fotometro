@props(['title' => null])

<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 -960 960 960" fill="none" aria-hidden="{{ $title ? 'false' : 'true' }}" role="{{ $title ? 'img' : 'presentation' }}">
    @if ($title)
        <title>{{ $title }}</title>
    @endif
    <path d="M695-40v-165H265q-24 0-42-18t-18-42v-430H40v-60h165v-165h60v655h655v60H755v165h-60Zm0-285v-370H325v-60h370q24 0 42 18t18 42v370h-60Z" fill="currentColor"/>
</svg>
