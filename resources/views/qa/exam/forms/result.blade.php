@extends('layouts.app')

{{-- Hasil ujian tertulis untuk agent & QA (PRD QA Assessment §5). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B --}}

@section('title', 'Hasil Ujian')

@section('content')
    @php
        $form = $assignment->form;
        $answers = $assignment->answers->keyBy('question_id');
        $published = $assignment->status === 'published';
        $showScore = ! $isAgent || $published;
    @endphp

    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">{{ $form->title }}</h1>
        <p class="text-sm text-ink-500">
            Attempt ke-{{ $assignment->attempt_ke }}
            &middot; Dikumpulkan {{ $assignment->completed_at?->format('d M Y H:i') ?? '—' }}
        </p>
    </div>

    @if (! $published)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            @if ($assignment->status === 'submitted')
                Jawaban Anda sudah dikumpulkan dan sedang menunggu penilaian QA.
            @elseif ($assignment->status === 'graded')
                Penilaian sudah selesai. Hasil akan ditampilkan setelah dipublikasikan QA.
            @else
                Ujian belum selesai dikerjakan.
            @endif
        </div>
    @endif

    @if ($showScore)
        <div class="card card-body mb-5 grid gap-4 text-sm sm:grid-cols-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-ink-500">Skor</p>
                <p class="font-semibold text-ink-900">{{ $assignment->total_score ?? '—' }} / {{ $assignment->max_score ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-ink-500">Persentase</p>
                <p class="font-semibold text-ink-900">
                    {{ $assignment->percentage !== null ? rtrim(rtrim(number_format((float) $assignment->percentage, 2, '.', ''), '0'), '.') . '%' : '—' }}
                </p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-ink-500">Hasil</p>
                <p class="font-semibold {{ $assignment->result === 'pass' ? 'text-green-600' : ($assignment->result === 'fail' ? 'text-red-600' : 'text-ink-900') }}">
                    {{ $assignment->result ? ($assignment->result === 'pass' ? 'Lulus' : 'Tidak lulus') : '—' }}
                </p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-ink-500">Ambang lulus</p>
                <p class="font-semibold text-ink-900">{{ $form->passing_score !== null ? $form->passing_score . '%' : '—' }}</p>
            </div>
        </div>
    @endif

    <h2 class="mb-3 text-lg font-semibold text-ink-900">Rincian Jawaban</h2>
    <div class="space-y-3">
        @foreach ($form->questions as $question)
            @php
                $answer = $answers->get($question->id);
                $isEssay = $question->question_type_snapshot === 'essay';
                $selected = (array) ($answer?->answer['selected'] ?? ($answer?->answer['value'] ?? []));
            @endphp
            <div class="card card-body">
                <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                    <h3 class="text-sm font-semibold text-ink-900">{{ $loop->iteration }}. {{ $question->question_snapshot }}</h3>
                    @if ($showScore)
                        <x-badge :variant="($answer?->score ?? 0) >= $question->point ? 'green' : 'gray'">
                            {{ $answer?->score ?? 0 }} / {{ $question->point }} poin
                        </x-badge>
                    @endif
                </div>

                @if ($isEssay)
                    <p class="whitespace-pre-line rounded-lg bg-ink-50 p-3 text-sm text-ink-700">{{ $answer?->answer['value'] ?? 'Tidak dijawab' }}</p>
                @else
                    <div class="space-y-1">
                        @foreach ($question->options_snapshot ?? [] as $option)
                            @php
                                $chosen = in_array($option['label'], $selected, true);
                                $correct = ! empty($option['is_correct']);
                            @endphp
                            <div class="flex items-center gap-2 text-sm">
                                <span class="w-6 font-semibold text-ink-500">{{ $option['label'] }}</span>
                                <span class="{{ $showScore && $correct ? 'font-medium text-green-700' : 'text-ink-700' }}">{{ $option['text'] }}</span>
                                @if ($chosen)<x-badge variant="brand">Jawaban Anda</x-badge>@endif
                                @if ($showScore && $correct)<x-badge variant="green">Kunci</x-badge>@endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($showScore && $answer?->grader_notes)
                    <p class="mt-2 text-xs text-ink-500">Catatan penilai: {{ $answer->grader_notes }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-5">
        @if ($isAgent)
            <a href="{{ route('qa.exam.forms.index') }}" class="btn-secondary">Kembali</a>
        @else
            <a href="{{ route('qa.exam.grading.show', $assignment) }}" class="btn-secondary">Kembali ke Penilaian</a>
        @endif
    </div>
@endsection
