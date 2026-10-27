{{-- AGENT: asisten | 2026-10-10 | kelas default jadi alert Tema A (emerald, kartu rata) | kelas lama `text-green-600` bertabrakan dengan kelas yang dikirim pemanggil (`text-emerald-700`) sehingga warna bergantung urutan CSS | UI/UX --}}
@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm text-emerald-800', 'role' => 'status']) }}>
        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
        </svg>
        <span>{{ $status }}</span>
    </div>
@endif
