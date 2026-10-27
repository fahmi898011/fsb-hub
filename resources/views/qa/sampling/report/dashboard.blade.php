@extends('layouts.app')

{{-- Dashboard QA (PRD Employee KPI/QA §39). --}}
{{-- AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting --}}

@section('title', 'Dashboard QA Sampling')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Dashboard QA Sampling</h1>
        <p class="text-sm text-ink-500">
            Ringkasan capaian sampling, kualitas skor, dan parameter yang paling sering bermasalah.
        </p>
    </div>

    @include('qa.sampling.report._nav')

    @include('qa.sampling.report._filters', ['showScore' => false])

    {{-- Baris 1: capaian sampling (PRD §39). --}}
    <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-report-stat label="Target" :value="number_format($progress['target'])" />
        <x-report-stat label="Completed" :value="number_format($progress['completed'])" />
        <x-report-stat label="Remaining" :value="number_format($progress['remaining'])" />
        <x-report-stat label="Completion"
                       :value="number_format($progress['completion'], 2).'%'"
                       :variant="$progress['completion'] >= 100 ? 'good' : ($progress['completion'] >= 75 ? 'warn' : 'bad')" />
    </div>

    @if ($progress['target'] === 0)
        {{-- Target 0 hampir selalu berarti aturan belum diisi, bukan capaian
             yang gagal. Pesannya harus mengarahkan ke tindakan, bukan sekadar
             menampilkan 0% yang membuat pembaca menebak-nebak. --}}
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Belum ada aturan Target Sampling yang berlaku untuk filter ini, sehingga capaian ditampilkan 0%.
            @can('create', App\Models\QaSamplingTarget::class)
                <a href="{{ route('admin.kpi.qa-targets.create') }}" class="font-semibold underline">Tambahkan aturan target</a>.
            @endcan
        </div>
    @endif

    {{-- Baris 2: kualitas skor + aktivitas minggu berjalan. --}}
    <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-report-stat label="Average Quality Score"
                       :value="$summary['avg_score'] === null ? '—' : number_format($summary['avg_score'], 2).'%'"
                       :hint="$summary['completed'].' assessment selesai'"
                       :variant="$summary['avg_score'] !== null && $summary['avg_score'] >= 90 ? 'good' : 'default'" />
        <x-report-stat label="Skor Terendah"
                       :value="$summary['min_score'] === null ? '—' : number_format($summary['min_score'], 2).'%'" />
        <x-report-stat label="Assessment Minggu Ini" :value="number_format($thisWeek['total'])" :hint="'Minggu '.now()->isoFormat('W')" />
        <x-report-stat label="Pending Review" :value="number_format($thisWeek['pending_review'])" hint="Menunggu pemeriksaan"
                       :variant="$thisWeek['pending_review'] > 0 ? 'warn' : 'good'" />
    </div>

    @if ($summary['zero_tolerance_count'] > 0)
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <strong>{{ number_format($summary['zero_tolerance_count']) }} assessment</strong>
            memicu Zero Tolerance (skor dipaksa 0) pada rentang ini.
        </div>
    @endif

    {{-- Grafik: tren per periode + distribusi skor. Keduanya menyajikan angka
         yang SAMA dengan tabel di bawahnya; grafik mempermudah melihat bentuk,
         tabel menjaga angkanya tetap dapat dibaca presisi dan dicetak. --}}
    <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="card card-body">
            <h2 class="mb-3 text-sm font-semibold text-ink-700">Tren Rata-rata Skor per Periode</h2>
            @if ($trend->isEmpty())
                <p class="text-sm text-ink-400">Belum ada data periode pada rentang ini.</p>
            @else
                <div data-chart="area"
                     data-chart-height="280"
                     data-chart-categories='@json($trend->pluck('period'))'
                     data-chart-series='@json([['name' => 'Rata-rata Skor', 'data' => $trend->pluck('avg_score')]])'></div>
            @endif
        </div>

        <div class="card card-body">
            <h2 class="mb-3 text-sm font-semibold text-ink-700">Distribusi Skor Akhir</h2>
            @if (array_sum($distribution) === 0)
                <p class="text-sm text-ink-400">Belum ada skor akhir pada rentang ini.</p>
            @else
                <div data-chart="donut"
                     data-chart-height="280"
                     data-chart-categories='@json(array_keys($distribution))'
                     data-chart-series='@json(array_values($distribution))'></div>
            @endif
        </div>
    </div>

    {{-- Tabel pendukung: angka presisi untuk hal yang tidak terbaca dari grafik. --}}
    <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b border-ink-100 px-5 py-3">
                <h2 class="text-sm font-semibold text-ink-700">Jumlah per Status</h2>
            </div>
            <table class="w-full text-left text-sm">
                <tbody class="divide-y divide-ink-100">
                    @foreach (['draft' => 'Draft', 'submitted' => 'Terkirim', 'reviewed' => 'Diperiksa', 'locked' => 'Terkunci', 'cancelled' => 'Dibatalkan'] as $status => $label)
                        <tr>
                            <td class="px-5 py-2.5 text-ink-600">{{ $label }}</td>
                            <td class="px-5 py-2.5 text-right font-medium text-ink-900">{{ number_format($statusCounts[$status] ?? 0) }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-ink-50/60">
                        <td class="px-5 py-2.5 font-semibold text-ink-700">Total</td>
                        <td class="px-5 py-2.5 text-right font-semibold text-ink-900">{{ number_format($statusCounts['total'] ?? 0) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-ink-100 px-5 py-3">
                <h2 class="text-sm font-semibold text-ink-700">Rata-rata per Scorecard</h2>
            </div>
            @if ($averages['scorecards']->isEmpty())
                <p class="px-5 py-4 text-sm text-ink-400">Belum ada assessment selesai.</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-2.5 font-semibold">Scorecard</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Dinilai</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Rata-rata</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($averages['scorecards'] as $row)
                            <tr class="hover:bg-ink-50">
                                <td class="px-5 py-2.5 text-ink-700">{{ $row['label'] }}</td>
                                <td class="px-5 py-2.5 text-right text-ink-500">{{ $row['total'] }}</td>
                                <td class="px-5 py-2.5 text-right font-medium text-ink-900">{{ number_format($row['avg_score'], 2) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Parameter paling bermasalah: inti PRD §40 "Parameter yang paling sering
         mendapat NO" dan "penalty terbesar". Dibatasi 5 karena dashboard adalah
         pintu masuk; daftar utuhnya ada di halaman Analitik Parameter. --}}
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-ink-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-ink-700">5 Parameter Paling Sering NO</h2>
            <a href="{{ route('qa.sampling.report.parameters', $filters) }}" class="btn-ghost btn-sm text-ink-600">Lihat semua</a>
        </div>
        @if ($topParameters->isEmpty())
            <p class="px-5 py-4 text-sm text-ink-400">Belum ada parameter yang dinilai pada rentang ini.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-2.5 font-semibold">Parameter</th>
                        <th class="px-5 py-2.5 text-right font-semibold">Dinilai</th>
                        <th class="px-5 py-2.5 text-right font-semibold">NO</th>
                        <th class="px-5 py-2.5 text-right font-semibold">NO Rate</th>
                        <th class="px-5 py-2.5 text-right font-semibold">Total Penalti</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($topParameters as $row)
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
                            <td class="px-5 py-2.5 text-right text-ink-700">{{ number_format($row['total_penalty'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

@endsection

@push('scripts')
    @include('qa.sampling.report._charts')
@endpush
