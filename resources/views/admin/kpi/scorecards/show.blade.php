@extends('layouts.app')

{{-- Detail scorecard + daftar versinya (PRD Employee KPI/QA §6). --}}
{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}

@section('title', $scorecard->name)

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">{{ $scorecard->name }}</h1>
        <p class="text-sm text-ink-500">
            {{ $scorecard->kpi?->name ?? '—' }}
            &middot; {{ $scorecard->channel?->name ?? 'tanpa channel' }}
            &middot; {{ $scorecard->kpi?->division?->name ?? 'tanpa divisi' }}
        </p>
    </div>

    @include('admin.kpi._nav')

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap justify-end gap-2">
        @can('update', $scorecard)
            <a href="{{ route('admin.kpi.scorecards.edit', $scorecard) }}" class="btn-secondary">Edit Scorecard</a>
        @endcan
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                <tr>
                    <th class="px-5 py-3 font-semibold">Versi</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 font-semibold">Berlaku</th>
                    <th class="px-5 py-3 font-semibold">Kategori</th>
                    <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-100">
                @forelse ($versions as $version)
                    <tr class="hover:bg-ink-50">
                        <td class="px-5 py-3 font-medium text-ink-800">v{{ $version->version }}</td>
                        <td class="px-5 py-3">
                            @php
                                $map = ['draft' => ['amber', 'Draft'], 'active' => ['green', 'Aktif'], 'inactive' => ['gray', 'Nonaktif'], 'archived' => ['gray', 'Arsip']];
                                [$v, $l] = $map[$version->status] ?? ['gray', $version->status];
                            @endphp
                            <x-badge :variant="$v">{{ $l }}</x-badge>
                        </td>
                        <td class="px-5 py-3 text-ink-600">
                            {{ $version->effective_from?->format('d/m/Y') ?? '—' }}
                            &ndash;
                            {{ $version->effective_to?->format('d/m/Y') ?? 'sekarang' }}
                        </td>
                        <td class="px-5 py-3 text-ink-600">{{ $version->categories_count }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.kpi.versions.show', $version) }}" class="btn-secondary btn-sm">Kelola Isi</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-6 text-center text-ink-500">Belum ada versi. Buat versi pertama di bawah.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @can('update', $scorecard)
        <div class="card card-body mt-5 max-w-2xl">
            <h2 class="mb-1 text-base font-semibold text-ink-900">Buat Versi Baru</h2>
            <p class="mb-4 text-sm text-ink-500">
                Untuk mengubah bobot, buat versi baru (jangan sunting versi lama) agar histori penilaian tetap utuh.
            </p>

            <form method="POST" action="{{ route('admin.kpi.scorecards.versions.store', $scorecard) }}" class="space-y-4">
                @csrf

                <div>
                    <label for="from_version_id" class="form-label">Salin dari versi</label>
                    <select id="from_version_id" name="from_version_id" class="form-input">
                        <option value="">— mulai kosong —</option>
                        @foreach ($versions as $version)
                            <option value="{{ $version->id }}" @selected((int) old('from_version_id') === $version->id)>
                                v{{ $version->version }} ({{ $version->status }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-ink-400">Menyalin kategori & parameter mempercepat perubahan bobot.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="effective_from" class="form-label">Berlaku mulai</label>
                        <input id="effective_from" name="effective_from" type="date" value="{{ old('effective_from') }}" class="form-input">
                    </div>
                    <div>
                        <label for="notes" class="form-label">Catatan</label>
                        <input id="notes" name="notes" type="text" value="{{ old('notes') }}" class="form-input" placeholder="mis. penyesuaian bobot Oktober">
                    </div>
                </div>

                <button type="submit" class="btn-primary">Buat Versi</button>
            </form>
        </div>
    @endcan
@endsection
