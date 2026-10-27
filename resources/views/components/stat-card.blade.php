{{-- Kartu angka dashboard.
     Satu angka tanpa konteks adalah dekorasi, jadi `hint` dipakai untuk
     memberi arti: periode hitungan, pembanding, atau umur data tertua.
     Bila `href` diisi, seluruh kartu menjadi tautan ke daftar yang sudah
     tersaring — angka di dashboard harus bisa langsung ditindaklanjuti.
     `tone` hanya mewarnai garis atas, dan hanya dipakai bila warnanya
     membawa arti (menunggu tindakan / selesai), bukan sebagai hiasan. --}}
@props([
    'label',
    'value',
    'hint' => null,
    'href' => null,
    'tone' => 'default',
])

@php
    $accents = [
        'default' => 'bg-ink-200',
        'calm' => 'bg-emerald-400',
        'alert' => 'bg-amber-400',
        'danger' => 'bg-red-500',
    ];
    $accent = $accents[$tone] ?? $accents['default'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge([
        'class' => 'group relative block overflow-hidden rounded-xl border border-ink-200/80 bg-white p-4 shadow-card transition-colors '
            . ($href
                ? 'hover:border-ink-300 hover:bg-ink-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2'
                : ''),
    ]) }}
>
    <span class="absolute inset-x-0 top-0 h-0.5 {{ $accent }}" aria-hidden="true"></span>

    <p class="flex items-start justify-between gap-2 text-[11px] font-semibold uppercase leading-snug tracking-wide text-ink-500">
        <span>{{ $label }}</span>
        @if ($href)
            <svg class="mt-px h-3.5 w-3.5 shrink-0 text-ink-300 transition-colors group-hover:text-ink-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
            </svg>
        @endif
    </p>

    <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight text-ink-900">{{ $value }}</p>

    @if ($hint)
        <p class="mt-1 text-xs leading-snug text-ink-400">{{ $hint }}</p>
    @endif
</{{ $tag }}>
