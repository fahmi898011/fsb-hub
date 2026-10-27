@extends('pdf.layout')

{{-- Hasil ujian tertulis seorang agent (PRD QA Assessment §5). --}}
{{-- AGENT: asisten | 2026-10-16 | buat berkas baru | modul Ekspor PDF --}}

@section('content')
    <table class="stats">
        <tr>
            <td>
                <div class="stat-label">Agent</div>
                <div class="stat-value" style="font-size: 10pt">{{ $assignment->agent?->name ?? '—' }}</div>
            </td>
            <td>
                <div class="stat-label">Skor</div>
                <div class="stat-value">
                    {{ $assignment->total_score !== null ? $assignment->total_score.' / '.$assignment->max_score : '—' }}
                </div>
            </td>
            <td>
                <div class="stat-label">Persentase</div>
                <div class="stat-value">
                    {{ $assignment->percentage !== null ? number_format((float) $assignment->percentage, 2).'%' : '—' }}
                </div>
            </td>
            <td>
                <div class="stat-label">Hasil</div>
                <div class="stat-value {{ $assignment->result === 'pass' ? 'good' : ($assignment->result === 'fail' ? 'bad' : '') }}">
                    {{ $assignment->result === 'pass' ? 'Lulus' : ($assignment->result === 'fail' ? 'Tidak lulus' : '—') }}
                </div>
            </td>
        </tr>
    </table>

    <table class="data" style="margin-bottom: 12px;">
        <tbody>
            <tr>
                <td style="width: 18%" class="muted">Form ujian</td>
                <td style="width: 32%">{{ $assignment->form?->title ?? '—' }}</td>
                <td style="width: 18%" class="muted">Attempt</td>
                <td style="width: 32%">ke-{{ $assignment->attempt_ke }}</td>
            </tr>
            <tr>
                <td class="muted">Mulai</td>
                <td>{{ $assignment->started_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="muted">Dikumpulkan</td>
                <td>{{ $assignment->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
            <tr>
                <td class="muted">Dinilai oleh</td>
                <td>{{ $assignment->gradedBy?->name ?? '—' }}</td>
                <td class="muted">Dipublikasikan</td>
                <td>{{ $assignment->published_at?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Rincian Jawaban</div>

    @if ($assignment->form->questions->isEmpty())
        <div class="empty">Form ini tidak memiliki soal.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 4%">No</th>
                    <th style="width: 30%">Soal</th>
                    <th style="width: 38%">Jawaban Agent</th>
                    <th class="num" style="width: 8%">Skor</th>
                    <th class="num" style="width: 8%">Maks</th>
                    <th style="width: 12%">Catatan QA</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($assignment->form->questions as $question)
                    @php
                        $answer = $answers->get($question->id);
                        $value = $answer?->answer['value'] ?? null;
                        $selected = $answer?->answer['selected'] ?? null;
                        $display = is_array($value)
                            ? implode(', ', $value)
                            : (string) ($value ?? (is_array($selected) ? implode(', ', $selected) : (string) $selected));
                    @endphp
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $question->question_snapshot }}</td>
                        <td>{{ $display !== '' ? $display : '—' }}</td>
                        <td class="num strong">{{ $answer?->score !== null ? number_format((float) $answer->score, 2) : '—' }}</td>
                        <td class="num">{{ number_format((float) $question->point, 2) }}</td>
                        <td>{{ $answer?->grader_notes ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="note">
        Dokumen ini dihasilkan otomatis oleh sistem Pusat Informasi. Perubahan setelah
        publikasi hanya dapat dilakukan melalui modul penilaian ujian.
    </p>
@endsection
