@extends('layouts.app')

@section('title', $item->title)

@section('content')
    <nav class="mb-4 flex flex-wrap items-center gap-2 text-sm text-ink-500">
        <a href="{{ route('home') }}" class="hover:text-ink-800">Beranda</a>
        <span>/</span>
        <a href="{{ route('information.index') }}" class="hover:text-ink-800">Informasi</a>
        @if ($item->category)
            <span>/</span>
            <a href="{{ route('information.category', $item->category->slug) }}" class="hover:text-ink-800">{{ $item->category->name }}</a>
        @endif
    </nav>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Kolom utama --}}
        <div class="lg:col-span-2">
            <div class="card card-body">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($item->category)
                        <x-badge variant="brand">{{ $item->category->name }}</x-badge>
                    @endif
                    <x-status-badge :status="$item->status" />
                    @if ($item->document_number)
                        <span class="text-xs text-ink-400">{{ $item->document_number }}</span>
                    @endif
                </div>

                <h1 class="mt-3 text-xl font-semibold text-ink-900 sm:text-2xl">{{ $item->title }}</h1>

                @if ($item->description)
                    <p class="mt-2 text-sm text-ink-600">{{ $item->description }}</p>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-ink-500">
                    <span class="inline-flex items-center gap-1">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                            <circle cx="12" cy="12" r="2.5" />
                        </svg>
                        {{ $item->views_count }} dilihat
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" />
                        </svg>
                        {{ $item->downloads_count }} diunduh
                    </span>
                    @if ($item->published_at)
                        <span>Dipublikasikan {{ $item->published_at->translatedFormat('d F Y') }}</span>
                    @endif
                    @if ($item->updated_at)
                        <span>Diperbarui {{ $item->updated_at->translatedFormat('d M Y') }}</span>
                    @endif
                </div>

                @if ($item->tags->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($item->tags as $tag)
                            <x-badge variant="gray">#{{ $tag->name }}</x-badge>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Konten teks --}}
            @if ($item->content)
                <div class="card card-body mt-4">
                    <h2 class="text-sm font-semibold text-ink-900">Isi Dokumen</h2>
                    <div class="prose-doc mt-3">
                        {!! nl2br(e($item->content)) !!}
                    </div>
                </div>
            @endif

            {{-- Viewer dokumen --}}
            @if ($item->currentVersion)
                <div class="mt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-ink-900">Dokumen</h2>
                        <span class="text-xs text-ink-400">
                            Versi {{ $item->currentVersion->version }} · {{ $item->currentVersion->file_size_human }}
                        </span>
                    </div>

                    <div class="doc-viewer">
                        <object
                            data="{{ route('information.document', $item->slug) }}#toolbar=1&view=FitH"
                            type="application/pdf"
                            class="h-full w-full"
                        >
                            <div class="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
                                <p class="text-sm text-ink-500">Pratinjau PDF tidak tersedia di perangkat ini.</p>
                                <a href="{{ route('information.download', $item->slug) }}" class="btn-primary">Unduh Dokumen</a>
                            </div>
                        </object>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('information.document', $item->slug) }}" target="_blank" rel="noopener" class="btn-secondary">
                            Buka di tab baru
                        </a>
                        <a href="{{ route('information.download', $item->slug) }}" class="btn-primary">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" />
                            </svg>
                            Unduh
                        </a>
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar kanan --}}
        <aside class="space-y-4">
            {{-- Riwayat versi --}}
            <div class="card">
                <div class="border-b border-ink-100 px-5 py-3">
                    <h2 class="text-sm font-semibold text-ink-900">Riwayat Versi</h2>
                </div>
                <div class="divide-y divide-ink-100">
                    @forelse ($item->versions as $version)
                        <div class="flex items-start gap-3 px-5 py-3">
                            <span class="badge-brand">v{{ $version->version }}</span>
                            <div class="min-w-0">
                                <p class="text-sm text-ink-700">{{ $version->change_notes ?: 'Tanpa catatan perubahan.' }}</p>
                                <p class="mt-0.5 text-xs text-ink-400">
                                    {{ $version->created_at?->translatedFormat('d M Y') }}
                                    @if ($version->creator)
                                        · {{ $version->creator->name }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-4 text-sm text-ink-400">Belum ada versi dokumen.</p>
                    @endforelse
                </div>
            </div>

            {{-- Info kategori --}}
            @if ($item->category)
                <div class="card card-body">
                    <h2 class="text-sm font-semibold text-ink-900">Kategori</h2>
                    <a href="{{ route('information.category', $item->category->slug) }}" class="mt-2 inline-flex items-center gap-2 text-sm text-brand-600 hover:underline">
                        {{ $item->category->name }}
                    </a>
                </div>
            @endif

            {{-- PRD §12 — Informasi terkait --}}
            @if (isset($related) && $related->isNotEmpty())
                <div class="card">
                    <div class="border-b border-ink-100 px-5 py-3">
                        <h2 class="text-sm font-semibold text-ink-900">Informasi Terkait</h2>
                    </div>
                    <div class="divide-y divide-ink-100">
                        @foreach ($related as $rel)
                            <a href="{{ route('information.show', $rel->slug) }}" class="block px-5 py-3 transition-colors hover:bg-ink-50/60">
                                <p class="line-clamp-2 text-sm font-medium leading-snug text-ink-900">{{ $rel->title }}</p>
                                <p class="mt-1 flex items-center gap-2 text-xs text-ink-500">
                                    <span>{{ $rel->category?->name }}</span>
                                    @if ($rel->published_at)
                                        <span class="text-ink-300">·</span>
                                        <time datetime="{{ $rel->published_at->toDateString() }}">{{ $rel->published_at->translatedFormat('d M Y') }}</time>
                                    @endif
                                </p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>
    </div>
@endsection