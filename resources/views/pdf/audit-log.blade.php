@extends('pdf.layout')

{{-- Rekap Audit Log sesuai filter yang aktif (PRD §16). --}}
{{-- AGENT: asisten | 2026-10-16 | buat berkas baru | modul Ekspor PDF --}}

@section('content')
    @if ($logs->isEmpty())
        <div class="empty">Tidak ada entri audit yang cocok dengan filter ini.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 4%">No</th>
                    <th style="width: 13%">Waktu</th>
                    <th style="width: 14%">Pelaku</th>
                    <th style="width: 10%">Modul</th>
                    <th style="width: 16%">Aksi</th>
                    <th style="width: 16%">Entitas</th>
                    <th style="width: 8%">Hasil</th>
                    <th style="width: 19%">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $log->actor_name ?: ($log->user?->name ?? 'Sistem') }}</td>
                        <td>{{ $log->module ?: '—' }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->subject_label ?: '—' }}</td>
                        <td>
                            <span class="{{ $log->result === 'denied' || $log->result === 'failed' ? 'bad' : 'good' }}">
                                {{ $log->result ?: '—' }}
                            </span>
                        </td>
                        <td>{{ \Illuminate\Support\Str::limit((string) $log->description, 120) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="note">
            Menampilkan {{ number_format($logs->count()) }} entri
            @if (isset($truncated) && $truncated)
                (dibatasi {{ number_format($logs->count()) }} entri terbaru; persempit filter untuk daftar lebih lengkap).
            @endif
        </p>
    @endif
@endsection
