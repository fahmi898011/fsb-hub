@extends('layouts.app')
@section('title', $attempt->quiz_name_snapshot)
@section('content')
    <div class="mb-5"><h1 class="text-xl font-semibold">{{ $attempt->quiz_name_snapshot }}</h1><p class="text-sm text-ink-500">Versi {{ $attempt->version_number_snapshot }} · {{ $attempt->total_questions }} soal · Lulus {{ number_format($attempt->passing_score_snapshot, 2) }}%</p>@if($attempt->version->time_limit)<p class="mt-1 text-sm font-medium text-amber-700">Batas waktu tersisa: <time id="quiz-countdown" class="tabular-nums" aria-live="polite">--:--</time>. Jawaban akan dikumpulkan otomatis saat waktu habis.</p>@endif</div>
    <form id="quiz-attempt-form" method="POST" action="{{ route('quiz.attempts.submit', $attempt) }}" class="space-y-4" @if($attempt->version->time_limit)data-deadline="{{ $attempt->started_at->copy()->addMinutes($attempt->version->time_limit)->timestamp }}"@endif>@csrf
        @foreach($attempt->version->questions as $question)<fieldset class="card card-body"><legend class="mb-3 text-sm font-semibold text-ink-900">{{ $loop->iteration }}. {{ $question->question_snapshot }} <span class="text-xs font-normal text-ink-500">({{ $question->point_snapshot }} poin)</span></legend><div class="space-y-2">@foreach($question->options_snapshot ?? [] as $option)<label class="flex cursor-pointer items-start gap-3 rounded-lg border border-ink-100 p-3 hover:bg-ink-50"><input class="mt-1" type="{{ $question->question_type_snapshot==='multiple'?'checkbox':'radio' }}" name="answers[{{ $question->id }}][]" value="{{ $option['id'] }}"><span class="text-sm"><strong>{{ $option['label'] }}.</strong> {{ $option['text'] }}</span></label>@endforeach</div></fieldset>@endforeach
        <div class="sticky bottom-3 flex justify-end"><button class="btn-primary" onclick="return confirm('Kumpulkan jawaban sekarang? Setelah dikumpulkan, jawaban tidak dapat diubah.')">Kumpulkan Jawaban</button></div>
    </form>
    @if($attempt->version->time_limit)
        <script>
            (() => {
                const form = document.getElementById('quiz-attempt-form');
                const display = document.getElementById('quiz-countdown');
                const deadline = Number(form.dataset.deadline) * 1000;
                let submitting = false;
                const tick = () => {
                    const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
                    display.textContent = `${Math.floor(remaining / 60).toString().padStart(2, '0')}:${(remaining % 60).toString().padStart(2, '0')}`;
                    if (remaining === 0 && !submitting) {
                        submitting = true;
                        form.submit();
                    }
                };
                tick();
                window.setInterval(tick, 1000);
            })();
        </script>
    @endif
@endsection