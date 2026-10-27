@props(['item'])

{{--
    PRD §11 — Informasi tampil sebagai baris dokumen yang padat informasi,
    bukan kartu besar bergaya marketing.
--}}
<a href="{{ route('information.show', $item->slug) }}" class="doc-row group">
    <span class="doc-row-icon">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5M9 13h6M9 17h4" />
        </svg>
    </span>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
            <span class="font-medium text-brand-700">{{ $item->category?->name }}</span>
            @if ($item->document_number)
                <span class="text-ink-300">·</span>
                <span class="truncate text-ink-500">{{ $item->document_number }}</span>
            @endif
        </div>

        <h3 class="doc-row-title group-hover:text-brand-700">{{ $item->title }}</h3>

        @if ($item->description)
            <p class="doc-row-excerpt">{{ $item->description }}</p>
        @endif

        <div class="doc-row-meta">
            @if ($item->published_at)
                <time datetime="{{ $item->published_at->toDateString() }}">
                    {{ $item->published_at->translatedFormat('d M Y') }}
                </time>
            @endif
            <span class="inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                    <circle cx="12" cy="12" r="2.5" />
                </svg>
                {{ number_format($item->views_count ?? 0) }}
            </span>
            <span class="inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" />
                </svg>
                {{ number_format($item->downloads_count ?? 0) }}
            </span>
        </div>
    </div>

    <svg class="mt-1 h-5 w-5 shrink-0 text-ink-300 transition-colors group-hover:text-brand-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
    </svg>
</a>