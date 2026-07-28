@props(['photo'])
<a
    href="{{ route('photos.show', $photo) }}"
    {{ $attributes }}
    data-lightbox
    data-lightbox-image="{{ $photo->web_url }}"
    data-lightbox-title="{{ $photo->publicLabel() }}"
    @if ($photo->description) data-lightbox-description="{{ $photo->description }}" @endif
    @if ($photo->category) data-lightbox-category="{{ $photo->category->name }}" @endif
    data-lightbox-copyright="{{ $photo->copyright_notice }}"
    @if ($photo->credit_line) data-lightbox-credit="{{ $photo->credit_line }}" @endif
    data-lightbox-license="{{ $photo->license->label() }}"
    @if ($photo->taken_at) data-lightbox-taken-at="{{ $photo->taken_at->format('d/m/Y') }}" @endif
    data-lightbox-url="{{ route('photos.show', $photo) }}"
>{{ $slot }}</a>
