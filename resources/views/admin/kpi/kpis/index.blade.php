@extends('layouts.app')

{{-- Master KPI (PRD Employee KPI/QA §4). --}}
{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}

@section('title', 'Master KPI')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Master KPI</h1>
        <p class="text-sm text-ink-500">KPI menentukan apa yang diukur untuk tiap divisi (mis. Quality Score).</p>
    </div>

    @include('admin.kpi._nav')

    <div class="mb-4 flex justify-end">
        @can('create', App\Models\Kpi::class)
            <a href="{{ route('admin.kpi.kpis.create') }}" class="btn-primary">Tambah KPI</a>
        @endcan
    </div>

    <form method="GET" class="card card-body mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4 sm:items-end">
        <div class="sm:col-span-2">
            <label for="search" class="form-label">Cari</label>
            <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}" class="form-input" placeholder="Nama atau kode">
        </div>
        <div>
            <label for="department" class="form-label">Divisi</label>
            <select id="department" name="department" class="form-input">
                <option value="">Semua</option>
                @foreach ($divisions as $division)
                    <option value="{{ $division->id }}" @selected((int) ($filters['department'] ?? 0) === $division->id)>{{ $division->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <div class="flex-1">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary shrink-0 self-end">Terapkan</button>
        </div>
    </form>

    @if ($kpis->isEmpty())
        <x-empty-state title="Belum ada KPI" description="Tambahkan KPI pertama, lalu susun scorecard untuk tiap channel." />
    @else
        <div class="card overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">KPI</th>
                        <th class="px-5 py-3 font-semibold">Divisi</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Scorecard</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($kpis as $kpi)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-800">
                                    {{ $kpi->name }}
                                    @if ($kpi->code)<span class="ml-1 text-xs font-normal text-ink-400">({{ $kpi->code }})</span>@endif
                                </p>
                                <p class="text-xs text-ink-400">{{ $kpi->description ?: $kpi->slug }}</p>
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $kpi->division?->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <x-badge :variant="$kpi->status === 'active' ? 'green' : 'gray'">
                                    {{ $kpi->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                </x-badge>
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $kpi->scorecards_count }}</td>
                            <td class="px-5 py-3 text-right">
                                @can('update', $kpi)
                                    <a href="{{ route('admin.kpi.kpis.edit', $kpi) }}" class="btn-secondary btn-sm">Edit</a>
                                @endcan
                                @can('delete', $kpi)
                                    <form method="POST" action="{{ route('admin.kpi.kpis.destroy', $kpi) }}" class="inline"
                                          onsubmit="return confirm('Hapus KPI ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Hapus</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $kpis->links() }}</div>
    @endif
@endsection
