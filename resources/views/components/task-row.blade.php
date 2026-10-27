@props([
    'label',
    'count',
    'href',
    'hint' => null,
    'icon' => 'inbox',
])

@php
    // Ikon garis sederhana, satu warna. Warna tidak membawa arti di sini —
    // jenis tugas sudah dibaca dari labelnya, jadi ikon tidak perlu berwarna.
    $paths = [
        'exam' => 'M9 12h6m-6 4h4M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z',
        'grade' => 'm9 12 2 2 4-4M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4Z',
        'ticket' => 'M4 7h16v3a2 2 0 0 0 0 4v3H4v-3a2 2 0 0 0 0-4V7Z',
        'inbox' => 'M4 13h4l2 3h4l2-3h4M4 13V6a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v7m-16 0v5a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-5',
    ];
@endphp

<a href="{{ $href }}" class="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-ink-50/60 sm:px-5">
    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-ink-100 text-ink-500">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $paths[$icon] ?? $paths['inbox'] }}" />
        </svg>
    </span>

    <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-medium text-ink-900 group-hover:text-brand-700">{{ $label }}</span>
        @if ($hint)
            <span class="block truncate text-xs text-ink-500">{{ $hint }}</span>
        @endif
    </span>

    <span class="shrink-0 text-sm font-semibold tabular-nums text-ink-900">{{ number_format($count) }}</span>

    <svg class="h-4 w-4 shrink-0 text-ink-300 transition-colors group-hover:text-brand-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
    </svg>
</a>
