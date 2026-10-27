@extends('layouts.app')

{{-- Daftar assessment QA Sampling (PRD Employee KPI/QA §14/§39). --}}
{{-- AGENT: asisten | 2026-10-03 | buat berkas baru | modul KPI, fase 2 QA sampling --}}

@section('title', 'QA Sampling')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">QA Sampling</h1>
        <p class="text-sm text-ink-500">
            Penilaian sample per karyawan. Skor dihitung otomatis di server dari jawaban YES/NO/NA.
        </p>
    </div>

    <div class="mb-4 flex justify-end">
        @can('create', App\Models\QaSamplingAssessment::class)
            <a href="{{ route('qa.sampling.create') }}" class="btn-primary">Ambil Sample Baru</a>
        @endcan
    </div>

    <form method="GET" class="card card-body mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label for="search" class="form-label">Cari</label>
            <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}" class="form-input"
                   placeholder="Nama karyawan / sample ID">
        </div>
        <div>
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-input">
                <option value="">Semua</option>
                @foreach (['draft' => 'Draft', 'submitted' => 'Terkirim', 'reviewed' => 'Diperiksa', 'locked' => 'Terkunci', 'cancelled' => 'Dibatalkan'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="period" class="form-label">Periode</label>
            <input id="period" name="period" type="text" value="{{ $filters['period'] ?? '' }}" class="form-input" placeholder="mis. 2026-W40">
        </div>
        <div>
            <label for="from" class="form-label">Dari Tanggal</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="form-input">
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn-secondary w-full">Terapkan</button>
        </div>
    </form>

    @if ($assessments->isEmpty())
        <x-empty-state title="Belum ada assessment"
                       description="Ambil sample dari dashboard sumber, lalu nilai parameternya di sini." />
    @else
        <div class="card overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Tanggal</th>
                        <th class="px-5 py-3 font-semibold">Karyawan</th>
                        <th class="px-5 py-3 font-semibold">Scorecard</th>
                        <th class="px-5 py-3 font-semibold">QA</th>
                        <th class="px-5 py-3 font-semibold">Skor</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($assessments as $assessment)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3 text-ink-600">{{ $assessment->assessment_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-900">{{ $assessment->employee_name_snapshot ?? '—' }}</p>
                                <p class="text-xs text-ink-400">{{ $assessment->period ?? 'tanpa periode' }}</p>
                            </td>
                            <td class="px-5 py-3 text-ink-600">
                                {{ $assessment->scorecard_name_snapshot }}
                                @if ($assessment->version_number_snapshot)
                                    <span class="text-xs text-ink-400">v{{ $assessment->version_number_snapshot }}</span>
                                @endif
                                <p class="text-xs text-ink-400">{{ $assessment->channel_name_snapshot ?? 'tanpa channel' }}</p>
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $assessment->qa?->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @if ($assessment->final_score !== null)
                                    <span class="font-semibold {{ $assessment->zero_tolerance_triggered ? 'text-red-600' : 'text-ink-900' }}">
                                        {{ number_format((float) $assessment->final_score, 2) }}%
                                    </span>
                                    @if ($assessment->zero_tolerance_triggered)
                                        <p class="text-xs text-red-500">ZTP</p>
                                    @endif
                                @else
                                    <span class="text-ink-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <x-badge :variant="$assessment->status === 'locked' ? 'green' : ($assessment->status === 'cancelled' ? 'gray' : ($assessment->status === 'draft' ? 'amber' : 'brand'))">
                                    {{ $assessment->status_label }}
                                </x-badge>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('qa.sampling.show', $assessment) }}" class="btn-secondary btn-sm">Lihat</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $assessments->links() }}</div>
    @endif
@endsection
