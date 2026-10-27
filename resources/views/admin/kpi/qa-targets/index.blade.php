@extends('layouts.app')

{{-- Master Target Sampling (PRD Employee KPI/QA §17/§18). --}}
{{-- AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting --}}

@section('title', 'Target Sampling')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Target Sampling</h1>
        <p class="text-sm text-ink-500">
            Aturan berapa sample yang seharusnya diambil per periode, per divisi/scorecard. Menjadi angka pembanding dashboard (PRD §17).
        </p>
    </div>

    @include('admin.kpi._nav')

    <div class="mb-4 flex justify-end">
        @can('create', App\Models\QaSamplingTarget::class)
            <a href="{{ route('admin.kpi.qa-targets.create') }}" class="btn-primary">Tambah Aturan</a>
        @endcan
    </div>

    <form method="GET" class="card card-body mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
        <div>
            <label for="department_id" class="form-label">Divisi</label>
            <select id="department_id" name="department_id" class="form-input">
                <option value="">Semua</option>
                @foreach ($divisions as $division)
                    <option value="{{ $division->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $division->id)>{{ $division->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="scorecard_id" class="form-label">Scorecard</label>
            <select id="scorecard_id" name="scorecard_id" class="form-input">
                <option value="">Semua</option>
                @foreach ($scorecards as $scorecard)
                    <option value="{{ $scorecard->id }}" @selected((string) ($filters['scorecard_id'] ?? '') === (string) $scorecard->id)>{{ $scorecard->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-input">
                <option value="">Semua</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn-secondary w-full">Terapkan</button>
        </div>
    </form>

    @if ($targets->isEmpty())
        <x-empty-state title="Belum ada aturan target"
                       description="Tambahkan aturan seperti 5 sample per karyawan per minggu agar dashboard menampilkan capaian." />
    @else
        <div class="card overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Skop</th>
                        <th class="px-5 py-3 font-semibold">Target</th>
                        <th class="px-5 py-3 font-semibold">Frekuensi</th>
                        <th class="px-5 py-3 font-semibold">Berlaku Sejak</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($targets as $target)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-800">{{ $target->division?->name ?? 'Semua divisi' }}</p>
                                <p class="text-xs text-ink-400">{{ $target->scorecard?->name ?? 'Semua scorecard' }}</p>
                            </td>
                            <td class="px-5 py-3 text-ink-700">
                                <span class="font-semibold">{{ $target->target_value }}</span> sample
                                <p class="text-xs text-ink-400">{{ $target->target_type_label }}</p>
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $target->frequency_label }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $target->effective_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <x-badge :variant="$target->status === 'active' ? 'green' : 'gray'">
                                    {{ $target->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                </x-badge>
                            </td>
                            <td class="px-5 py-3 text-right">
                                @can('update', $target)
                                    <a href="{{ route('admin.kpi.qa-targets.edit', $target) }}" class="btn-secondary btn-sm">Edit</a>
                                @endcan
                                @can('delete', $target)
                                    <form method="POST" action="{{ route('admin.kpi.qa-targets.destroy', $target) }}" class="inline"
                                          onsubmit="return confirm('Hapus aturan ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Hapus</button>
                                    </form>
                                @endcan
                                {{-- Aturan yang sudah berlaku tidak dapat dihapus (menjaga angka
                                     historis). Beri tahu alasannya, bukan sekadar menghilangkan tombol. --}}
                                @cannot('delete', $target)
                                    <span class="text-xs text-ink-400"
                                          title="Aturan yang sudah berlaku tidak dihapus agar laporan periode lampau tidak berubah. Nonaktifkan bila tidak dipakai lagi.">
                                        Terkunci
                                    </span>
                                @endcannot
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $targets->links() }}</div>
    @endif
@endsection
