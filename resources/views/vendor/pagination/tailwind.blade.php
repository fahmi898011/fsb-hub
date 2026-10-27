{{--
    Pagination kustom (Laravel mencarinya otomatis di
    resources/views/vendor/pagination/tailwind.blade.php).

    Alasan dibuat sendiri: tampilan bawaan Laravel memakai palet `zinc`/`gray`,
    sedangkan aplikasi ini memakai `ink` + `brand`, sehingga warna tombol
    pagination terlihat berbeda dari tombol lain. Teks juga diterjemahkan.

    NB: sengaja TIDAK memakai @php(...) inline di sini — mencampurnya dengan
    blok @php ... @endphp membuat Blade diam-diam gagal mengompilasi @if
    tepat sebelumnya (lihat progres.md, "jebakan Blade").
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-ink-500">
            Menampilkan <span class="font-medium text-ink-700">{{ $paginator->firstItem() }}</span>
            &ndash;
            <span class="font-medium text-ink-700">{{ $paginator->lastItem() }}</span>
            dari <span class="font-medium text-ink-700">{{ $paginator->total() }}</span> data
        </p>

        <div class="flex items-center gap-1">
            {{-- Sebelumnya --}}
            @if ($paginator->onFirstPage())
                <span class="btn-ghost btn-sm cursor-not-allowed text-ink-300" aria-disabled="true" aria-label="Halaman sebelumnya">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-ghost btn-sm text-ink-600 hover:bg-ink-100" aria-label="Halaman sebelumnya">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
            @endif

            {{-- Nomor halaman (elemen "..." tidak bisa diklik) --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="btn-ghost btn-sm cursor-default text-ink-400" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn-primary btn-sm" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="btn-ghost btn-sm text-ink-600 hover:bg-ink-100" aria-label="Ke halaman {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Berikutnya --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-ghost btn-sm text-ink-600 hover:bg-ink-100" aria-label="Halaman berikutnya">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @else
                <span class="btn-ghost btn-sm cursor-not-allowed text-ink-300" aria-disabled="true" aria-label="Halaman berikutnya">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif
