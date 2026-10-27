@extends('layouts.app')

{{-- Daftar periode QA (PRD QA Assessment §7). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, periode --}}

@section('title', 'Periode QA')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Periode QA</h1>
            <p class="text-sm text-ink-500">Periode penilaian (bulanan/kuartal); bisa bersarang lewat periode induk.</p>
        </div>
        @can('create', App\Models\QaPeriod::class)
            <a href="{{ route('qa.periods.create') }}" class="btn-primary">Tambah Periode</a>
        @endcan
    </div>

    @include('qa.assessment._nav')

    <div class="mb-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="search" class="form-label">Cari</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-input" placeholder="Nama periode">
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary">Terapkan</button>
        </form>
    </div>

    @if ($periods->isEmpty())
        <x-empty-state title="Belum ada periode"
                       description="Buat periode penilaian sebelum membuat assessment." />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Nama</th>
                            <th class="px-5 py-3 font-semibold">Rentang</th>
                            <th class="px-5 py-3 font-semibold">Induk</th>
                            <th class="px-5 py-3 font-semibold">Assessment</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($periods as $period)
                            <tr class="hover:bg-ink-50">
                                <td class="px-5 py-3 font-medium text-ink-900">{{ $period->name }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $period->start_date->format('d M Y') }} — {{ $period->end_date->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $period->parent?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $period->assessments_count }}</td>
                                <td class="px-5 py-3">
                                    <x-badge :variant="$period->status === 'active' ? 'green' : 'gray'">
                                        {{ $period->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                    </x-badge>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('qa.periods.show', $period) }}" class="text-brand-700 hover:underline">Lihat</a>
                                    @can('update', $period)
                                        <a href="{{ route('qa.periods.edit', $period) }}" class="ml-3 text-brand-700 hover:underline">Ubah</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $periods->links() }}</div>
        </div>
    @endif
@endsection
