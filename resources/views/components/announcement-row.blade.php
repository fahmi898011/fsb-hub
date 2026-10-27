@props(['item'])

{{--
    Pengumuman adalah DERET WAKTU, bukan katalog dokumen.

    Dua keputusan bentuk yang berbeda dari `x-information-card`:
    1. Kolom pertama adalah tanggal, bukan ikon. Ikon speaker yang sama
       diulang di setiap baris tidak menambah informasi apa pun (semua baris
       memang pengumuman); tanggal justru satu-satunya pembeda yang dicari
       pembaca.
    2. Tanggal SELALU tampil, termasuk di 390px. Sebelumnya ia memakai
       `hidden sm:block`, sehingga di ponsel pengumuman kehilangan satu-
       satunya penanda kesegaran.

    Markup tetap memakai `.doc-row` agar padding, hover, dan ring fokus
    keyboard sama persis dengan daftar dokumen di bawahnya.
--}}
<a href="{{ route('information.show', $item->slug) }}" class="doc-row group">
    @if ($item->published_at)
        <time
            class="mt-0.5 w-14 shrink-0 text-xs font-semibold leading-tight text-ink-600"
            datetime="{{ $item->published_at->toDateString() }}"
            title="{{ $item->published_at->translatedFormat('d F Y') }}"
        >
            <span class="block">{{ $item->published_at->translatedFormat('d M') }}</span>
            <span class="mt-0.5 block text-[11px] font-medium text-ink-400">{{ $item->published_at->translatedFormat('Y') }}</span>
        </time>
    @endif

    <div class="min-w-0 flex-1">
        <h3 class="doc-row-title group-hover:text-brand-700">{{ $item->title }}</h3>

        @if ($item->description)
            {{-- Satu baris saja: pengumuman adalah teaser, isi lengkapnya ada di halaman detail. --}}
            <p class="mt-1 truncate text-sm text-ink-600">{{ $item->description }}</p>
        @endif
    </div>

    <svg class="mt-1 h-5 w-5 shrink-0 text-ink-300 transition-colors group-hover:text-brand-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
    </svg>
</a>
