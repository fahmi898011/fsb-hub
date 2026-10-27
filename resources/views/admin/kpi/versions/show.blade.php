@extends('layouts.app')

{{-- Workspace versi scorecard: kategori & parameter (PRD §6/§37). --}}
{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}

@section('title', 'Versi '.$version->version.' — '.$version->scorecard->name)

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">
            {{ $version->scorecard->name }} &middot; Versi {{ $version->version }}
        </h1>
        <p class="text-sm text-ink-500">
            {{ $version->scorecard->kpi?->name ?? '—' }}
            &middot; {{ $version->scorecard->channel?->name ?? 'tanpa channel' }}
        </p>
    </div>

    @include('admin.kpi._nav')

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    @php($isDraft = $version->status === 'draft')
    @php($totalOk = round($totalWeight, 2) === 100.00)
    @php($statusMap = ['draft' => 'Draft', 'active' => 'Aktif', 'inactive' => 'Nonaktif', 'archived' => 'Arsip'])

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="card card-body">
            <p class="text-xs uppercase tracking-wide text-ink-400">Status Versi</p>
            <p class="mt-1 text-lg font-semibold text-ink-900">{{ $statusMap[$version->status] ?? $version->status }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs uppercase tracking-wide text-ink-400">Total Bobot Dihitung</p>
            <p class="mt-1 text-lg font-semibold {{ $totalOk ? 'text-green-600' : 'text-amber-600' }}">
                {{ number_format($totalWeight, 2) }}%
            </p>
            <p class="text-xs text-ink-400">Harus 100% sebelum versi diaktifkan.</p>
        </div>
        <div class="card card-body flex flex-col justify-between">
            <p class="text-xs uppercase tracking-wide text-ink-400">Aksi Versi</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @can('update', $version)
                    @if ($isDraft)
                        <form method="POST" action="{{ route('admin.kpi.versions.activate', $version) }}">
                            @csrf
                            <button type="submit" class="btn-primary btn-sm" @disabled(! $totalOk)>Aktifkan Versi</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.kpi.versions.deactivate', $version) }}">
                            @csrf
                            <button type="submit" class="btn-secondary btn-sm">Kembalikan ke Draft</button>
                        </form>
                    @endif
                @endcan
                @can('delete', $version)
                    <form method="POST" action="{{ route('admin.kpi.versions.destroy', $version) }}" onsubmit="return confirm('Hapus versi ini?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Hapus Versi</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    @unless ($isDraft)
        <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
            Versi ini tidak lagi draft, jadi isinya terkunci. Untuk mengubah bobot, buat versi baru dari halaman scorecard.
        </div>
    @endunless

    <div class="mb-3 flex justify-end">
        @if ($isDraft)
            @can('update', $version)
                <a href="{{ route('admin.kpi.categories.create', $version) }}" class="btn-primary">Tambah Kategori</a>
            @endcan
        @endif
    </div>

    @include('admin.kpi.versions._categories')
@endsection
