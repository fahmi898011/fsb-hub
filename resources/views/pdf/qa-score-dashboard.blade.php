@extends('pdf.layout')

{{-- Skor QA per agent — rata-rata periode (bukan KPI final). --}}
{{-- AGENT: asisten | 2026-10-16 | buat berkas baru | modul KPI, skor QA --}}

@section('content')
    <table class="stats">
        <tr>
            <td>
                <div class="stat-label">Agent Dinilai</div>
                <div class="stat-value">{{ number_format($summary['agents'] ?? 0) }}</div>
            </td>
            <td>
                <div class="stat-label">Rata-rata Keseluruhan</div>
                <div class="stat-value">
                    {{ $summary['avg_score'] === null ? '—' : number_format($summary['avg_score'], 2).'%' }}
                </div>
            </td>
            <td>
                <div class="stat-label">Skor Tertinggi</div>
                <div class="stat-value">
                    {{ $summary['max_score'] === null ? '—' : number_format($summary['max_score'], 2).'%' }}
                </div>
            </td>
            <td>
                <div class="stat-label">Assessment</div>
                <div class="stat-value">{{ number_format($summary['assessments'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    <p class="note" style="margin-top: 0; margin-bottom: 10px;">
        Angka ini adalah <span class="strong">rata-rata skor sampling QA</span> per agent pada periode terpilih.
        Bukan skor KPI final — formula KPI menunggu keputusan tim QA.
    </p>

    @if ($agents->isEmpty())
        <div class="empty">Belum ada assessment pada rentang ini.</div>
    @else
        <div class="section-title">Peringkat Skor QA</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 5%">#</th>
                    <th style="width: 30%">Agent</th>
                    <th style="width: 18%">Divisi</th>
                    <th class="num" style="width: 10%">Dinilai</th>
                    <th class="num" style="width: 10%">Rata-rata</th>
                    <th class="num" style="width: 9%">Terendah</th>
                    <th class="num" style="width: 9%">Tertinggi</th>
                    <th class="num" style="width: 9%">Capaian</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($agents as $row)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td class="strong">{{ $row['name'] }}</td>
                        <td>{{ $row['division'] }}</td>
                        <td class="num">{{ number_format($row['total']) }}</td>
                        <td class="num strong {{ $row['avg_score'] >= 90 ? 'good' : ($row['avg_score'] < 70 ? 'bad' : '') }}">
                            {{ number_format($row['avg_score'], 2) }}%
                        </td>
                        <td class="num">{{ number_format($row['min_score'], 2) }}</td>
                        <td class="num">{{ number_format($row['max_score'], 2) }}</td>
                        <td class="num">
                            {{ $row['target'] > 0 ? number_format($row['completion'], 0).'%' : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($trend->isNotEmpty())
        <div class="section-title">Tren Rata-rata per Periode</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 30%">Periode</th>
                    <th class="num" style="width: 20%">Assessment</th>
                    <th class="num" style="width: 20%">Rata-rata</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($trend as $point)
                    <tr>
                        <td>{{ $point['period'] }}</td>
                        <td class="num">{{ number_format($point['total']) }}</td>
                        <td class="num strong">{{ number_format($point['avg_score'], 2) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
