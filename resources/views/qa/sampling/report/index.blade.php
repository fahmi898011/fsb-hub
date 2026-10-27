@extends('layouts.app')

{{-- Laporan QA Assessment berfilter (PRD Employee KPI/QA §42). --}}
{{-- AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting --}}

@section('title', 'Laporan QA Sampling')

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Laporan QA Assessment</h1>
            <p class="text-sm text-ink-500">
                Filter Period, Division, Employee, QA, Channel, Scorecard, dan Score (PRD §42).
            </p>
        </div>
        {{-- Ekspor memakai filter yang sedang aktif — `$filters` sudah bersih
             dari controller, jadi tautan ini tidak bisa dipakai menyusupkan
             parameter liar ke query. --}}
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('qa.sampling.report.pdf', $filters) }}" class="btn-secondary">
                Ekspor PDF
            </a>
            <a href="{{ route('qa.sampling.report.index', $filters + ['format' => 'csv']) }}" class="btn-secondary">
                Unduh CSV
            </a>
        </div>
    </div>

    @include('qa.sampling.report._nav')

    @include('qa.sampling.report._filters')

    <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-report-stat label="Assessment Selesai" :value="number_format($summary['completed'])" />
        <x-report-stat label="Rata-rata Skor"
                       :value="$summary['avg_score'] === null ? '—' : number_format($summary['avg_score'], 2).'%'"
                       :variant="$summary['avg_score'] !== null && $summary['avg_score'] >= 90 ? 'good' : 'default'" />
        <x-report-stat label="Skor Terendah"
                       :value="$summary['min_score'] === null ? '—' : number_format($summary['min_score'], 2).'%'" />
        <x-report-stat label="Skor Tertinggi"
                       :value="$summary['max_score'] === null ? '—' : number_format($summary['max_score'], 2).'%'" />
    </div>

    @if ($assessments->isEmpty())
        <x-empty-state title="Tidak ada data"
                       description="Tidak ada assessment yang cocok dengan filter ini. Coba lebarkan rentang tanggalnya." />
    @else
        <div class="card overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Tanggal</th>
                        <th class="px-5 py-3 font-semibold">Karyawan</th>
                        <th class="px-5 py-3 font-semibold">Divisi</th>
                        <th class="px-5 py-3 font-semibold">Scorecard</th>
                        <th class="px-5 py-3 font-semibold">QA</th>
                        <th class="px-5 py-3 text-right font-semibold">Skor</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($assessments as $assessment)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3 text-ink-600">
                                {{ $assessment->assessment_date?->format('d M Y') ?? '—' }}
                                <p class="text-xs text-ink-400">{{ $assessment->period ?: 'tanpa periode' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-900">{{ $assessment->employee_name_snapshot ?: ($assessment->employee?->name ?? '—') }}</p>
                                <p class="text-xs text-ink-400">{{ $assessment->channel_name_snapshot ?? 'tanpa channel' }}</p>
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $assessment->division?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $assessment->scorecard_name_snapshot }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $assessment->qa?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                @if ($assessment->final_score === null)
                                    <span class="text-ink-400">—</span>
                                @else
                                    <span class="font-semibold {{ $assessment->zero_tolerance_triggered ? 'text-red-600' : 'text-ink-900' }}">
                                        {{ number_format((float) $assessment->final_score, 2) }}%
                                    </span>
                                    @if ($assessment->zero_tolerance_triggered)
                                        <p class="text-xs text-red-500">ZTP</p>
                                    @endif
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
