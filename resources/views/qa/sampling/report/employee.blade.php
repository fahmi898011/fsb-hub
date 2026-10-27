@extends('layouts.app')

{{-- Riwayat penilaian karyawan (PRD Employee KPI/QA §39/§42). --}}
{{-- AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting --}}

@section('title', 'Riwayat Karyawan')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Riwayat Penilaian Karyawan</h1>
        <p class="text-sm text-ink-500">
            Perkembangan skor satu karyawan dari waktu ke waktu (PRD §19/§39).
        </p>
    </div>

    @include('qa.sampling.report._nav')

    @include('qa.sampling.report._filters', ['showScore' => false])

    <form method="GET" class="card card-body mb-5 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="employee_id" class="form-label">Pilih Karyawan</label>
            <select id="employee_id" name="employee_id" class="form-input">
                <option value="">— Pilih karyawan yang punya hasil —</option>
                @foreach ($employees as $item)
                    <option value="{{ $item->id }}" @selected((string) ($employee?->id) === (string) $item->id)>{{ $item->name }}</option>
                @endforeach
            </select>
        </div>
        {{-- Filter lain diteruskan agar berpindah karyawan tidak diam-diam
             melepas rentang tanggal yang sedang dilihat. --}}
        @foreach (['from', 'to', 'period', 'department_id', 'scorecard_id'] as $key)
            @if (! empty($filters[$key]))
                <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">
            @endif
        @endforeach
        <button type="submit" class="btn-primary sm:shrink-0">Tampilkan</button>
    </form>

    @if (! $employee)
        <x-empty-state title="Belum ada karyawan dipilih"
                       description="Pilih karyawan untuk melihat riwayat skornya." />
    @else
        @php($scores = $history->map(fn ($a) => $a->final_score === null ? null : (float) $a->final_score))
        @php($average = $scores->filter(fn ($s) => $s !== null)->avg())

        <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-report-stat label="Karyawan" :value="$employee->name"
                           :hint="$history->first()?->division?->name ?? 'Divisi tidak tercatat pada rentang ini'" />
            <x-report-stat label="Jumlah Penilaian" :value="number_format($history->count())" hint="Pada rentang filter" />
            <x-report-stat label="Rata-rata Skor"
                           :value="$average === null ? '—' : number_format($average, 2).'%'"
                           :variant="$average !== null && $average >= 90 ? 'good' : 'default'" />
        </div>

        @if ($history->isEmpty())
            <x-empty-state title="Belum ada penilaian"
                           description="Karyawan ini belum punya assessment selesai pada rentang yang dipilih." />
        @else
            <div class="card card-body mb-5">
                <h2 class="mb-3 text-sm font-semibold text-ink-700">Tren Skor</h2>
                <div data-chart="line"
                     data-chart-height="300"
                     data-chart-categories='@json($history->map(fn ($a) => $a->assessment_date?->format('d M Y')))'
                     data-chart-series='@json([['name' => 'Skor Akhir', 'data' => $scores]])'></div>
            </div>

            <div class="card overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Tanggal</th>
                            <th class="px-5 py-3 font-semibold">Periode</th>
                            <th class="px-5 py-3 font-semibold">Scorecard</th>
                            <th class="px-5 py-3 font-semibold">QA</th>
                            <th class="px-5 py-3 text-right font-semibold">Skor</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($history as $assessment)
                            <tr class="hover:bg-ink-50">
                                <td class="px-5 py-3 text-ink-600">{{ $assessment->assessment_date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-5 py-3 text-ink-500">{{ $assessment->period ?: '—' }}</td>
                                <td class="px-5 py-3 text-ink-600">
                                    {{ $assessment->scorecard_name_snapshot }}
                                    @if ($assessment->version_number_snapshot)
                                        <span class="text-xs text-ink-400">v{{ $assessment->version_number_snapshot }}</span>
                                    @endif
                                </td>
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

            @if ($parameters->isNotEmpty())
                <div class="card mt-5 overflow-hidden">
                    <div class="border-b border-ink-100 px-5 py-3">
                        <h2 class="text-sm font-semibold text-ink-700">Parameter Paling Sering NO untuk Karyawan Ini</h2>
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                            <tr>
                                <th class="px-5 py-2.5 font-semibold">Parameter</th>
                                <th class="px-5 py-2.5 text-right font-semibold">Dinilai</th>
                                <th class="px-5 py-2.5 text-right font-semibold">NO</th>
                                <th class="px-5 py-2.5 text-right font-semibold">NO Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($parameters->take(10) as $row)
                                <tr class="hover:bg-ink-50">
                                    <td class="px-5 py-2.5">
                                        <p class="font-medium text-ink-800">{{ $row['parameter'] }}</p>
                                        <p class="text-xs text-ink-400">{{ $row['category'] }}</p>
                                    </td>
                                    <td class="px-5 py-2.5 text-right text-ink-500">{{ number_format($row['total']) }}</td>
                                    <td class="px-5 py-2.5 text-right text-ink-700">{{ number_format($row['no_count']) }}</td>
                                    <td class="px-5 py-2.5 text-right font-medium {{ $row['no_rate'] > 20 ? 'text-red-600' : 'text-ink-900' }}">
                                        {{ number_format($row['no_rate'], 2) }}%
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    @endif
@endsection

@push('scripts')
    @include('qa.sampling.report._charts')
@endpush
