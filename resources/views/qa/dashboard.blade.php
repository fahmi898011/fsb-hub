@extends('layouts.app')

@section('title', 'Dashboard QA')

@section('content')
    @php
        // Angka aktivitas selalu menyebut periodenya, dan bila ada pembanding
        // angka itu ikut ditampilkan. Tanpa itu, "4.231" tidak berarti apa-apa.
        $trendHint = fn (?int $trend) => $trend === null
            ? '7 hari terakhir · belum ada pembanding'
            : '7 hari terakhir · ' . ($trend >= 0 ? '+' : '-') . abs($trend) . '% dari 7 hari sebelumnya';

        $draftHint = $stats['draft'] === 0
            ? 'Tidak ada draft menunggu'
            : ($oldestDraftDays === 0
                ? 'Tertua menunggu sejak hari ini'
                : 'Tertua menunggu ' . $oldestDraftDays . ' hari');

        $itemMeta = fn ($item) => trim(
            ($item->category?->name ?? 'Tanpa kategori') . ' · ' . ($item->updated_at?->diffForHumans() ?? ''),
            ' ·'
        );
    @endphp

    {{-- Topbar sudah merender judul halaman; di sini cukup konteks + aksi. --}}
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        {{-- Kalimat ini sebelumnya berbunyi "dalam 7 hari terakhir" padahal
             tiga kartu di bawahnya (total/terbit/draft) menghitung SELURUH
             data. Hanya angka aktivitas yang berjendela 7 hari. --}}
        <p class="text-sm text-ink-500">Status konten saat ini, dengan aktivitas pembaca 7 hari terakhir.</p>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('qa.information.index') }}" class="btn-secondary">Kelola Informasi</a>
            <a href="{{ route('qa.information.create') }}" class="btn-primary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
                Tambah
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <x-stat-card
            label="Dipublikasikan"
            :value="$stats['published']"
            :hint="'dari ' . number_format($stats['total']) . ' informasi'"
            :href="route('qa.information.index', ['status' => 'published'])"
            tone="calm"
        />

        <x-stat-card
            label="Draft"
            :value="$stats['draft']"
            :hint="$draftHint"
            :href="route('qa.information.index', ['status' => 'draft'])"
            :tone="$stats['draft'] > 0 ? 'alert' : 'default'"
        />

        <x-stat-card
            label="Dilihat"
            :value="number_format($stats['views'])"
            :hint="$trendHint($viewsTrend)"
        />

        <x-stat-card
            label="Diunduh"
            :value="number_format($stats['downloads'])"
            :hint="$trendHint($downloadsTrend)"
        />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-panel
            title="Informasi per Kategori"
            description="Dokumen terbit saja, sama seperti di beranda."
        >
            @forelse ($byCategory as $row)
                @php
                    $percent = $stats['published'] > 0
                        ? (int) round($row->total / $stats['published'] * 100)
                        : 0;
                    $categoryUrl = $row->category
                        ? route('qa.information.index', ['category' => $row->category->slug])
                        : null;
                @endphp

                <{{ $categoryUrl ? 'a' : 'div' }}
                    @if ($categoryUrl) href="{{ $categoryUrl }}" @endif
                    class="block px-5 py-3 sm:px-6 {{ $categoryUrl ? 'transition-colors hover:bg-ink-50/60' : '' }}"
                >
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="truncate text-sm text-ink-700">{{ $row->category?->name ?? 'Tanpa kategori' }}</span>
                        <span class="text-sm font-semibold tabular-nums text-ink-900">{{ number_format($row->total) }}</span>
                    </div>

                    <div class="mt-2 h-1 overflow-hidden rounded-full bg-ink-100">
                        <div class="h-full rounded-full bg-brand-500" style="width: {{ max($percent, 2) }}%"></div>
                    </div>
                </{{ $categoryUrl ? 'a' : 'div' }}>
            @empty
                <p class="px-5 py-6 text-sm text-ink-400">Belum ada dokumen terbit.</p>
            @endforelse
        </x-panel>

        <x-panel
            class="lg:col-span-2"
            title="Terakhir Diperbarui"
            description="Enam perubahan terakhir, termasuk draft."
            :href="route('qa.information.index')"
        >
            <div class="divide-y divide-ink-100">
                @forelse ($recent as $item)
                    <x-list-row
                        :href="route('qa.information.show', $item->slug)"
                        :title="$item->title"
                        :meta="$itemMeta($item)"
                    >
                        <x-status-badge :status="$item->status" />
                    </x-list-row>
                @empty
                    <p class="px-5 py-6 text-sm text-ink-400">Belum ada informasi.</p>
                @endforelse
            </div>
        </x-panel>
    </div>

    <x-panel
        class="mt-6"
        title="Paling Banyak Dibaca"
        description="7 hari terakhir · 5 teratas"
    >
        <div class="flex items-center justify-between gap-3 border-b border-ink-100 bg-ink-50/60 px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-ink-400 sm:px-6">
            <span>Informasi</span>
            <span class="flex shrink-0 gap-6">
                <span class="w-12 text-right">Dilihat</span>
                <span class="w-12 text-right">Diunduh</span>
            </span>
        </div>

        <div class="divide-y divide-ink-100">
            @forelse ($topRead as $item)
                <x-list-row
                    :href="route('qa.information.show', $item->slug)"
                    :title="$item->title"
                    :meta="$item->category?->name ?? 'Tanpa kategori'"
                >
                    <span class="flex shrink-0 gap-6 text-sm tabular-nums">
                        <span class="w-12 text-right font-semibold text-ink-800">{{ number_format($item->views_count) }}</span>
                        <span class="w-12 text-right text-ink-500">{{ number_format($item->downloads_count) }}</span>
                    </span>
                </x-list-row>
            @empty
                <p class="px-5 py-6 text-sm text-ink-400">Belum ada aktivitas baca dalam 7 hari terakhir.</p>
            @endforelse
        </div>
    </x-panel>
@endsection

