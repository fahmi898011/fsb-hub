@extends('pdf.layout')

{{-- Laporan assessment QA Sampling (PRD Employee KPI/QA §42). --}}
{{-- AGENT: asisten | 2026-10-16 | buat berkas baru | modul Ekspor PDF --}}

@section('content')
    <table class="stats">
        <tr>
            <td>
                <div class="stat-label">Jumlah Assessment</div>
                <div class="stat-value">{{ number_format($summary['completed'] ?? 0) }}</div>
            </td>
            <td>
                <div class="stat-label">Rata-rata Skor</div>
                <div class="stat-value">
                    {{ $summary['avg_score'] === null ? '—' : number_format($summary['avg_score'], 2).'%' }}
                </div>
            </td>
            <td>
                <div class="stat-label">Skor Terendah</div>
                <div class="stat-value">
                    {{ $summary['min_score'] === null ? '—' : number_format($summary['min_score'], 2).'%' }}
                </div>
            </td>
            <td>
                <div class="stat-label">Zero Tolerance</div>
                <div class="stat-value">{{ number_format($summary['zero_tolerance_count'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    @if ($assessments->isEmpty())
        <div class="empty">Tidak ada assessment yang cocok dengan filter ini.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 4%">No</th>
                    <th style="width: 9%">Tanggal</th>
                    <th style="width: 8%">Periode</th>
                    <th style="width: 15%">Karyawan</th>
                    <th style="width: 11%">Divisi</th>
                    <th style="width: 13%">Scorecard</th>
                    <th style="width: 10%">Channel</th>
                    <th style="width: 10%">QA</th>
                    <th class="num" style="width: 6%">Awal</th>
                    <th class="num" style="width: 6%">Penalti</th>
                    <th class="num" style="width: 6%">Akhir</th>
                    <th style="width: 8%">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($assessments as $row)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $row->assessment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $row->period ?: '—' }}</td>
                        <td>{{ $row->employee_name_snapshot ?: ($row->employee?->name ?? '—') }}</td>
                        <td>{{ $row->division?->name ?? '—' }}</td>
                        <td>{{ $row->scorecard_name_snapshot ?: '—' }}</td>
                        <td>{{ $row->channel_name_snapshot ?: '—' }}</td>
                        <td>{{ $row->qa?->name ?? '—' }}</td>
                        <td class="num">{{ number_format((float) $row->initial_score, 2) }}</td>
                        <td class="num">{{ number_format((float) $row->penalty_total, 2) }}</td>
                        <td class="num strong">{{ number_format((float) $row->final_score, 2) }}</td>
                        <td>
                            {{ $row->status_label }}
                            @if ($row->zero_tolerance_triggered)
                                <span class="bad">(ZTP)</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="note">
            Menampilkan {{ number_format($assessments->count()) }} baris
            @if (isset($truncated) && $truncated)
                (dibatasi {{ number_format($assessments->count()) }} baris pertama; persempit filter untuk daftar lengkap).
            @endif
        </p>
    @endif
@endsection
