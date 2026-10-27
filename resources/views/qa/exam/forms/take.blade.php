@extends('layouts.app')

{{-- Pengerjaan ujian tertulis oleh agent (PRD QA Assessment §5). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B --}}

@section('title', 'Kerjakan Ujian')

@section('content')
    @php
        $questions = $assignment->form->questions;
        $totalPoints = $questions->sum('point');
    @endphp

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">{{ $assignment->form->title }}</h1>
            <p class="text-sm text-ink-500">
                {{ $questions->count() }} soal &middot; {{ $totalPoints }} poin
                @if ($assignment->form->passing_score !== null)
                    &middot; Lulus {{ $assignment->form->passing_score }}%
                @endif
            </p>
        </div>
        @if ($timeRemaining !== null)
            <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700"
                 data-deadline="{{ now()->addSeconds($timeRemaining)->timestamp }}">
                Sisa waktu: <time id="exam-countdown" class="tabular-nums">--:--</time>
            </div>
        @endif
    </div>

    @if ($assignment->form->description)
        <div class="card card-body mb-4 whitespace-pre-line text-sm text-ink-600">{{ $assignment->form->description }}</div>
    @endif

    {{-- Form tanpa soal tidak boleh tampil sebagai halaman kosong: peserta
         mengira ujiannya rusak. Pesannya harus menjelaskan harus menghubungi siapa.
         AGENT: asisten | 2026-10-20 | pesan form tanpa soal | modul QA Assessment --}}
    @if ($questions->isEmpty())
        <div class="card card-body text-sm text-ink-600">
            Form ini belum punya soal, jadi belum bisa dikerjakan. Hubungi QA yang mengirim ujian ini.
        </div>
    @else
    <form id="exam-form" method="POST" action="{{ route('qa.exam.forms.finish', $assignment) }}" class="space-y-4">
        @csrf

        @foreach ($questions as $question)
            @php
                $existing = $answers->get($question->id);
                $saved = $existing?->answer ?? [];
                $selected = $saved['selected'] ?? (array) ($saved['value'] ?? []);
                $essayValue = is_array($saved['value'] ?? null) ? '' : ($saved['value'] ?? '');
            @endphp

            <fieldset class="card card-body"
                      data-question-id="{{ $question->id }}"
                      data-type="{{ $question->question_type_snapshot }}">
                <legend class="mb-3 text-sm font-semibold text-ink-900">
                    {{ $loop->iteration }}. {{ $question->question_snapshot }}
                    <span class="text-xs font-normal text-ink-500">({{ $question->point }} poin)</span>
                </legend>

                @if ($question->question_type_snapshot === 'multiple_choice')
                    <div class="space-y-2">
                        @foreach ($question->options_snapshot ?? [] as $option)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-ink-100 p-3 hover:bg-ink-50">
                                <input class="mt-1" type="radio"
                                       name="answers[{{ $question->id }}]"
                                       value="{{ $option['label'] }}"
                                       @checked(in_array($option['label'], $selected, true))>
                                <span class="text-sm">
                                    <strong>{{ $option['label'] }}.</strong> {{ $option['text'] }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <textarea class="form-input w-full" rows="5"
                              name="answers[{{ $question->id }}]"
                              placeholder="Tulis jawaban Anda di sini...">{{ $essayValue }}</textarea>
                @endif

                <p class="mt-2 text-xs text-ink-400" data-save-status>Tersimpan otomatis.</p>
            </fieldset>
        @endforeach

        <div class="sticky bottom-3 flex justify-end">
            <button type="submit" class="btn-primary">
                Kumpulkan Jawaban
            </button>
        </div>
    </form>

    <script>
        (function () {
            const form = document.getElementById('exam-form');
            const submitUrl = @json(route('qa.exam.forms.submit-answer', $assignment));
            const token = @json(csrf_token());
            const fieldsets = Array.from(form.querySelectorAll('fieldset[data-question-id]'));

            async function saveFieldset(fieldset) {
                const status = fieldset.querySelector('[data-save-status]');
                const body = new FormData();
                body.append('_token', token);
                body.append('question_id', fieldset.dataset.questionId);

                if (fieldset.dataset.type === 'multiple_choice') {
                    const checked = fieldset.querySelector('input[type="radio"]:checked');
                    if (!checked) return true;
                    body.append('answer[selected][]', checked.value);
                } else {
                    body.append('answer[value]', fieldset.querySelector('textarea').value);
                }

                if (status) status.textContent = 'Menyimpan...';
                try {
                    const res = await fetch(submitUrl, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        body,
                    });
                    if (status) status.textContent = res.ok
                        ? 'Tersimpan ' + new Date().toLocaleTimeString('id-ID')
                        : 'Gagal menyimpan.';
                    return res.ok;
                } catch (e) {
                    if (status) status.textContent = 'Gagal menyimpan, coba lagi.';
                    return false;
                }
            }

            fieldsets.forEach((fieldset) => {
                fieldset.querySelectorAll('input[type="radio"]').forEach((input) => {
                    input.addEventListener('change', () => saveFieldset(fieldset));
                });
                const textarea = fieldset.querySelector('textarea');
                if (textarea) {
                    let timer;
                    textarea.addEventListener('input', () => {
                        clearTimeout(timer);
                        timer = setTimeout(() => saveFieldset(fieldset), 800);
                    });
                }
            });

            let submitting = false;
            form.addEventListener('submit', function (e) {
                if (submitting) return;
                if (!confirm('Kumpulkan jawaban sekarang? Jawaban tidak dapat diubah setelah dikumpulkan.')) {
                    e.preventDefault();
                    return;
                }
                e.preventDefault();
                submitting = true;
                Promise.all(fieldsets.map(saveFieldset)).then(() => form.submit());
            });

            const box = document.querySelector('[data-deadline]');
            if (box) {
                const display = document.getElementById('exam-countdown');
                const deadline = Number(box.dataset.deadline) * 1000;
                let autoSubmitted = false;
                const tick = () => {
                    const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
                    display.textContent = `${Math.floor(remaining / 60).toString().padStart(2, '0')}:${(remaining % 60).toString().padStart(2, '0')}`;
                    if (remaining === 0 && !autoSubmitted) {
                        autoSubmitted = true;
                        submitting = true;
                        Promise.all(fieldsets.map(saveFieldset)).then(() => form.submit());
                    }
                };
                tick();
                window.setInterval(tick, 1000);
            }
        })();
    </script>
    @endif
@endsection
