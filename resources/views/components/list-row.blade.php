{{-- Satu bentuk baris untuk semua daftar di dashboard.
     `title` + `meta` di kiri, isi slot (badge/angka) di kanan. Bila `href`
     diisi, baris menjadi tautan dengan fokus keyboard yang terlihat. --}}
@props([
    'href' => null,
    'title',
    'meta' => null,
])

@php($tag = $href ? 'a' : 'div')

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge([
        'class' => 'flex items-center gap-3 px-5 py-3 sm:px-6 transition-colors '
            . ($href
                ? 'hover:bg-ink-50/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500'
                : ''),
    ]) }}
>
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-ink-800">{{ $title }}</p>
        @if ($meta)
            <p class="mt-0.5 truncate text-xs text-ink-400">{{ $meta }}</p>
        @endif
    </div>

    {{ $slot }}
</{{ $tag }}>
