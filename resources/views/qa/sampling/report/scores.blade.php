@extends('layouts.app')

{{-- Skor QA per agent (rata-rata periode) — BUKAN KPI final. --}}
{{-- AGENT: asisten | 2026-10-16 | buat berkas baru | modul KPI, skor QA --}}

@section('title', 'Skor QA per Agent')

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Skor QA (Rata-rata Periode)</h1>
            <p class="text-sm text-ink-500">
                Rata-rata skor sampling per agent pada rentang terpilih, beserta peringkat,
                tren, dan capaian jumlah sample.
            </p>
        </div>

        <a href="{{ route('qa.sampling.report.scores.pdf', $filters) }}" class="btn-secondary btn-sm shrink-0">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0 3-3m-3 3-3-3M4 17v2h16v-2M6 4h12v9H6z" />
            </svg>
            Ekspor PDF
        </a>
    </div>

    @include('qa.sampling.report._nav')

    @include('qa.sampling.report._filters', ['showScore' => false])

    {{-- Peringatan batas makna angka. Ditempatkan di atas, bukan catatan kaki:
         tanpa ini, "Skor QA" mudah dibaca sebagai "nilai KPI". --}}
    <div class="mb-5 rounded-xl border border-ink-200 bg-ink-50 px-4 py-3 text-sm text-ink-600">
        Angka di halaman ini adalah <strong>rata-rata skor sampling QA</strong>, bukan skor KPI final.
        Formula KPI masih menunggu keputusan tim QA.
    </div>

    <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-report-stat label="Agent Dinilai" :value="number_format($summary['agents'])" :hint="number_format($summary['assessments']).' assessment selesai'" />
        <x-report-stat label="Rata-rata Keseluruhan"
                       :value="$summary['avg_score'] === null ? '—' : number_format($summary['avg_score'], 2).'%'"
                       :variant="$summary['avg_score'] !== null && $summary['avg_score'] >= 90 ? 'good' : 'default'" />
        <x-report-stat label="Skor Tertinggi"
                       :value="$summary['max_score'] === null ? '—' : number_format($summary['max_score'], 2).'%'" />
        <x-report-stat label="Capaian Sampling"
                       :value="number_format($progress['completion'], 2).'%'"
                       :hint="$progress['completed'].' dari '.$progress['target'].' target'"
                       :variant="$progress['completion'] >= 100 ? 'good' : ($progress['completion'] >= 75 ? 'warn' : 'bad')" />
    </div>
    @if ($agents->isEmpty())
        <div class="card card-body text-sm text-ink-500">
            Belum ada assessment selesai pada rentang ini. Ubah filter atau isi penilaian sampling terlebih dahulu.
        </div>
    @else
        {{-- Grafik: peringkat 10 besar + tren periode. Angka presisi tetap ada di
             tabel bawah agar grafik tidak menjadi satu-satunya sumber. --}}
        <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="card card-body">
                <h2 class="mb-3 text-sm font-semibold text-ink-700">10 Agent dengan Skor Tertinggi</h2>
                <div data-chart="bar"
                     data-chart-height="300"
                     data-chart-categories='@json($topAgents->pluck('name'))'
                     data-chart-series='@json([['name' => "Rata-rata Skor", "data" => $topAgents->pluck("avg_score")]])'></div>
            </div>

            <div class="card card-body">
                <h2 class="mb-3 text-sm font-semibold text-ink-700">Tren Rata-rata Skor per Periode</h2>
                @if ($trend->isEmpty())
                    <p class="text-sm text-ink-400">Belum ada data periode pada rentang ini.</p>
                @else
                    <div data-chart="area"
                         data-chart-height="300"
                         data-chart-categories='@json($trend->pluck('period'))'
                         data-chart-series='@json([['name' => "Rata-rata Skor", "data" => $trend->pluck("avg_score")]])'></div>
                @endif
            </div>
        </div>

        <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="card card-body lg:col-span-2">
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

            <div class="card overflow-hidden">
                <div class="border-b border-ink-100 px-5 py-3">
                    <h2 class="text-sm font-semibold text-ink-700">Perlu Perhatian</h2>
                    <p class="text-xs text-ink-400">5 skor terendah pada rentang ini</p>
                </div>
                <ul class="divide-y divide-ink-100 text-sm">
                    @foreach ($bottomAgents as $row)
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                            <a href="{{ route('qa.sampling.report.employees.show', ['employee' => $row['employee_id']] + $filters) }}"
                               class="truncate text-ink-700 hover:text-brand-700 hover:underline">{{ $row['name'] }}</a>
                            <span class="shrink-0 font-medium {{ $row['avg_score'] < 70 ? 'text-red-600' : 'text-amber-600' }}">
                                {{ number_format($row['avg_score'], 2) }}%
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        {{-- Tabel lengkap: sumber angka presisi + tautan ke riwayat tiap agent. --}}
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-100 px-5 py-3">
                <h2 class="text-sm font-semibold text-ink-700">Peringkat Lengkap</h2>
                <span class="text-xs text-ink-400">{{ number_format($agents->count()) }} agent</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-2.5 font-semibold">#</th>
                            <th class="px-5 py-2.5 font-semibold">Agent</th>
                            <th class="px-5 py-2.5 font-semibold">Divisi</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Dinilai</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Rata-rata</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Terendah</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Tertinggi</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Capaian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($agents as $row)
                            <tr class="hover:bg-ink-50">
                                <td class="px-5 py-2.5 text-ink-400">{{ $loop->iteration }}</td>
                                <td class="px-5 py-2.5">
                                    <a href="{{ route('qa.sampling.report.employees.show', ['employee' => $row['employee_id']] + $filters) }}"
                                       class="font-medium text-brand-700 hover:underline">{{ $row['name'] }}</a>
                                </td>
                                <td class="px-5 py-2.5 text-ink-600">{{ $row['division'] }}</td>
                                <td class="px-5 py-2.5 text-right text-ink-600">{{ number_format($row['total']) }}</td>
                                <td class="px-5 py-2.5 text-right font-semibold {{ $row['avg_score'] >= 90 ? 'text-emerald-600' : ($row['avg_score'] < 70 ? 'text-red-600' : 'text-ink-900') }}">
                                    {{ number_format($row['avg_score'], 2) }}%
                                </td>
                                <td class="px-5 py-2.5 text-right text-ink-500">{{ number_format($row['min_score'], 2) }}</td>
                                <td class="px-5 py-2.5 text-right text-ink-500">{{ number_format($row['max_score'], 2) }}</td>
                                <td class="px-5 py-2.5 text-right {{ $row['target'] === 0 ? 'text-ink-400' : ($row['completion'] >= 100 ? 'text-emerald-600' : 'text-amber-600') }}">
                                    {{ $row['target'] > 0 ? number_format($row['completion'], 0).'%' : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @include('qa.sampling.report._charts')
@endpush

