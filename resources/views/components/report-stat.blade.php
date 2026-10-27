{{-- Kartu angka ringkas untuk dashboard laporan.
     AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting
     Props: label, value, hint (opsional), variant (opsional: default|good|warn|bad) --}}
@props(['label', 'value', 'hint' => null, 'variant' => 'default'])

@php($tone = [
    'default' => 'text-ink-900',
    'good' => 'text-emerald-600',
    'warn' => 'text-amber-600',
    'bad' => 'text-red-600',
][$variant] ?? 'text-ink-900')

<div class="card card-body">
    <p class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold {{ $tone }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-ink-400">{{ $hint }}</p>
    @endif
</div>
