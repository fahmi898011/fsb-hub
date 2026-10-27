@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    {{-- PRD §8/§10 — tanpa hero, gradient, atau kartu pembuka.
         Header adalah baris teks: identitas halaman, konteks hari ini, lalu
         SATU pintu masuk pencarian. --}}
    <header class="mb-8">
        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
            <h1 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">
                Pusat Informasi Karyawan
            </h1>

            <p class="text-sm text-ink-500">
                <time datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('l, d F Y') }}</time>
                <span class="mx-1.5 text-ink-300" aria-hidden="true">·</span>
                <span class="tabular-nums">{{ number_format($totalPublished) }}</span> dokumen
                <span class="mx-1.5 text-ink-300" aria-hidden="true">·</span>
                <span class="tabular-nums">{{ number_format($categories->count()) }}</span> kategori
            </p>
        </div>

        <div class="mt-4 max-w-2xl">
            <x-search-bar size="lg" placeholder="Cari dokumen, SOP, atau nomor..." shortcut />
        </div>
    </header>

    {{-- Tugas saya — hanya dirender bila memang ada yang menunggu.
         Beranda sebelumnya buta peran: agent tidak melihat ujiannya, QA tidak
         melihat antrean penilaian, IT tidak melihat tiket yang ditugaskan. --}}
    @if (! empty($tasks))
        <section class="mb-8" aria-labelledby="tugas-saya">
            <h2 id="tugas-saya" class="section-title mb-3">Tugas saya</h2>

            <div class="card divide-y divide-ink-100">
                @foreach ($tasks as $task)
                    <x-task-row
                        :label="$task['label']"
                        :hint="$task['hint']"
                        :count="$task['count']"
                        :href="$task['url']"
                        :icon="$task['icon']"
                    />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Kategori sebagai chip, bukan grid kartu: satu baris yang bisa
         dipindai, dengan jumlah dokumen terbit sebagai konteks. --}}
    @if ($categories->isNotEmpty())
        <section class="mb-8" aria-labelledby="kategori">
            <div class="mb-3 flex items-baseline justify-between gap-3">
                <h2 id="kategori" class="section-title">Kategori</h2>
                <a href="{{ route('information.index') }}" class="link text-sm">Semua informasi</a>
            </div>

            <nav class="flex flex-wrap gap-2" aria-label="Kategori informasi">
                @foreach ($categories as $category)
                    <a
                        href="{{ route('information.category', $category->slug) }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-ink-200 bg-white px-3 py-1.5 text-sm text-ink-700 transition-colors hover:border-brand-300 hover:bg-brand-50/40 hover:text-brand-700"
                    >
                        {{ $category->name }}
                        <span class="text-xs tabular-nums text-ink-400">{{ number_format($category->items_count ?? 0) }}</span>
                    </a>
                @endforeach
            </nav>
        </section>
    @endif

    {{-- Pengumuman --}}
    @if ($announcements->isNotEmpty())
        <section class="mb-8" aria-labelledby="pengumuman">
            <h2 id="pengumuman" class="section-title mb-3">Pengumuman</h2>
            <div class="doc-list">
                @foreach ($announcements as $item)
                    <x-announcement-row :item="$item" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Dua kolom hanya bila kolom kanan benar-benar ada isinya. Daftar
         terbaru memakai lebar penuh saat belum ada dokumen yang pernah dibuka,
         bukan menyisakan kolom kosong selebar sepertiga layar. --}}
    <div class="grid grid-cols-1 gap-8 {{ $popular->isNotEmpty() ? 'lg:grid-cols-3' : '' }}">
        <section class="{{ $popular->isNotEmpty() ? 'lg:col-span-2' : '' }}" aria-labelledby="terbaru">
            <div class="mb-3 flex items-baseline justify-between gap-3">
                <h2 id="terbaru" class="section-title">Informasi terbaru</h2>
                <a href="{{ route('information.index') }}" class="link text-sm">Lihat semua</a>
            </div>

            @if ($latest->isEmpty())
                <x-empty-state title="Belum ada informasi" description="Dokumen yang dipublikasikan akan muncul di sini." />
            @else
                <div class="doc-list">
                    @foreach ($latest as $item)
                        <x-information-card :item="$item" />
                    @endforeach
                </div>
            @endif
        </section>

        @if ($popular->isNotEmpty())
            <section aria-labelledby="populer">
                <h2 id="populer" class="section-title mb-3">Paling banyak dibuka</h2>

                <div class="card divide-y divide-ink-100">
                    @foreach ($popular as $index => $item)
                        <a href="{{ route('information.show', $item->slug) }}" class="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-ink-50/60">
                            <span class="mt-0.5 w-4 shrink-0 text-right text-xs font-semibold tabular-nums text-ink-400">
                                {{ $index + 1 }}
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="line-clamp-2 block text-sm font-medium leading-snug text-ink-900">{{ $item->title }}</span>
                                <span class="mt-0.5 block text-xs text-ink-500">{{ $item->category?->name }}</span>
                            </span>

                            <span class="shrink-0 text-xs tabular-nums text-ink-500" title="{{ number_format($item->views_count) }} kali dibuka">
                                {{ number_format($item->views_count) }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- Satu baris bantuan, bukan kartu CTA berwarna. --}}
    <p class="mt-8 text-sm text-ink-500">
        Tidak menemukan yang dicari?
        <a href="{{ route('tickets.create') }}" class="link">Buat tiket ke IT</a>.
    </p>
@endsection
