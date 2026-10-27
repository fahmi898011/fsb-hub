{{-- Kartu berjudul untuk dashboard.
     Menyatukan header yang sebelumnya ditulis ulang di setiap blok: judul,
     satu baris keterangan (periode/urutan), dan satu tautan keluar. --}}
@props([
    'title',
    'description' => null,
    'href' => null,
    'linkLabel' => 'Lihat semua',
])

<section {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    <div class="card-header">
        <div class="min-w-0">
            <h2 class="truncate text-sm font-semibold text-ink-900">{{ $title }}</h2>
            @if ($description)
                <p class="mt-0.5 truncate text-xs text-ink-500">{{ $description }}</p>
            @endif
        </div>

        @if ($href)
            <a href="{{ $href }}" class="shrink-0 text-xs font-medium text-brand-700 hover:underline">{{ $linkLabel }}</a>
        @endif
    </div>

    {{ $slot }}
</section>
