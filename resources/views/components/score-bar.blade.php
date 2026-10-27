{{-- Batang skor 0–100 untuk tabel dashboard.

     Angka mentah ("64.0" di antara "82.0") hanya bisa dibandingkan dengan
     membaca satu per satu; batang membuat urutannya terbaca sekali lihat.
     Warnanya HANYA dipakai bila ada artinya — di bawah `threshold` berarti
     perlu pembinaan. Untuk data yang "makin besar makin buruk" (mis. NO rate),
     kirim `tone` eksplisit alih-alih memakai ambang.

     Angka aslinya selalu ada di sebelah batang, jadi batang ini dekoratif
     (`aria-hidden`) dan tidak boleh menjadi satu-satunya sumber informasi. --}}
@props([
    'value' => null,
    'max' => 100,
    'threshold' => null,
    'tone' => 'auto',
])

@php
    $score = is_numeric($value) ? (float) $value : null;
    $percent = $score === null ? 0 : max(0, min(100, ($score / max((float) $max, 1)) * 100));

    $fill = match (true) {
        $tone === 'danger' => 'bg-red-400',
        $tone === 'default' => 'bg-brand-500',
        $threshold !== null && $score !== null && $score < (float) $threshold => 'bg-red-400',
        default => 'bg-brand-500',
    };
@endphp

<div {{ $attributes->merge(['class' => 'h-1 overflow-hidden rounded-full bg-ink-100']) }} aria-hidden="true">
    @if ($score !== null)
        <div class="h-full rounded-full {{ $fill }}"
             style="width: {{ $score > 0 ? max($percent, 2) : 0 }}%"></div>
    @endif
</div>
