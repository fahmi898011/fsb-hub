@extends('layouts.app')

{{-- Penilaian jawaban ujian per assignment (PRD QA Assessment §5). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B --}}

@section('title', 'Penilaian Ujian')

@section('content')
    @php
        $form = $assignment->form;
        $answers = $assignment->answers->keyBy('question_id');
        $canGrade = auth()->user()->can('grade', $assignment);
    @endphp

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">{{ $form->title }}</h1>
            <p class="text-sm text-ink-500">
                Agent: <span class="font-medium text-ink-700">{{ $assignment->agent?->name }}</span>
                &middot; Attempt ke-{{ $assignment->attempt_ke }}
                @if ($form->passing_score !== null)
                    &middot; Ambang lulus {{ $form->passing_score }}%
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-badge :variant="match($assignment->status) {
                'published' => 'green',
                'graded' => 'brand',
                default => 'amber',
            }">{{ ucfirst(str_replace('_', ' ', $assignment->status)) }}</x-badge>
            @if ($assignment->status === 'graded')
                @can('publish', $assignment)
                    <form method="POST" action="{{ route('qa.exam.grading.publish', $assignment) }}" class="inline"
                          onsubmit="return confirm('Publikasikan hasil ke agent?')">
                        @csrf
                        <button type="submit" class="btn-primary">Publikasikan Hasil</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    @include('qa.assessment._nav')

    {{-- Lembar jawaban kertas. QA mengunggah foto/scan lalu menandai ujian
         sudah dikumpulkan; agent tidak menekan apa pun di sistem. Berkas
         disimpan di disk privat dan dibaca lewat rute ber-otorisasi, bukan
         URL publik. --}}
    {{-- AGENT: asisten | 2026-10-16 | tambah UI lembar jawaban | modul Quiz -> Ujian --}}
    @php
        $paperMode = App\Models\Setting::enabled('exam.paper_mode', true);
        $canUploadSheet = auth()->user()->can('uploadSheet', $assignment);
        $canViewSheet = auth()->user()->can('viewSheet', $assignment);
        $canMarkCollected = auth()->user()->can('markCollected', $assignment);
    @endphp

    @if ($paperMode && ($canUploadSheet || $assignment->answer_sheet_path))
        <div class="card card-body mb-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-ink-900">Lembar Jawaban Kertas</h2>
                    <p class="mt-1 text-xs text-ink-500">
                        @if ($assignment->answer_sheet_path)
                            Diunggah {{ $assignment->answer_sheet_uploaded_at?->format('d M Y H:i') ?? '—' }}
                            @if ($assignment->answerSheetUploadedBy)
                                oleh {{ $assignment->answerSheetUploadedBy->name }}
                            @endif
                        @else
                            Belum ada berkas. Unggah foto/scan (JPG, PNG, WEBP, atau PDF, maks. 5 MB).
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($assignment->answer_sheet_path && $canViewSheet)
                        <a href="{{ route('qa.exam.grading.sheet.show', $assignment) }}" target="_blank" rel="noopener" class="btn-secondary">
                            Lihat berkas
                        </a>
                    @endif

                    @if ($canUploadSheet && $assignment->answer_sheet_path)
                        <form method="POST" action="{{ route('qa.exam.grading.sheet.destroy', $assignment) }}"
                              onsubmit="return confirm('Hapus lembar jawaban yang sudah diunggah?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-secondary text-red-600">Hapus</button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($canUploadSheet)
                <form method="POST" action="{{ route('qa.exam.grading.sheet.upload', $assignment) }}"
                      enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="min-w-0 flex-1">
                        <label for="answer_sheet" class="form-label">Berkas lembar jawaban</label>
                        <input type="file" id="answer_sheet" name="answer_sheet"
                               accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-input w-full" required>
                        @error('answer_sheet')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn-primary">
                        {{ $assignment->answer_sheet_path ? 'Ganti berkas' : 'Unggah' }}
                    </button>
                </form>
            @endif

            @if ($canMarkCollected)
                <form method="POST" action="{{ route('qa.exam.grading.collect', $assignment) }}"
                      class="mt-3 border-t border-ink-100 pt-3"
                      onsubmit="return confirm('Tandai ujian ini sudah dikumpulkan dan masuk antrean penilaian?')">
                    @csrf
                    <button type="submit" class="btn-primary">Tandai Dikumpulkan</button>
                    <span class="ml-2 text-xs text-ink-500">
                        Status berubah menjadi &ldquo;submitted&rdquo;; soal yang belum dinilai dihitung 0.
                    </span>
                </form>
            @endif
        </div>
    @endif

    @if ($assignment->percentage !== null)
        <div class="card card-body mb-5 grid gap-4 text-sm sm:grid-cols-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-ink-500">Skor</p>
                <p class="font-semibold text-ink-900">{{ $assignment->total_score }} / {{ $assignment->max_score }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-ink-500">Persentase</p>
                <p class="font-semibold text-ink-900">{{ rtrim(rtrim(number_format((float) $assignment->percentage, 2, '.', ''), '0'), '.') }}%</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-ink-500">Hasil</p>
                <p class="font-semibold {{ $assignment->result === 'pass' ? 'text-green-600' : ($assignment->result === 'fail' ? 'text-red-600' : 'text-ink-900') }}">
                    {{ $assignment->result ? ($assignment->result === 'pass' ? 'Lulus' : 'Tidak lulus') : '—' }}
                </p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-ink-500">Dikumpulkan</p>
                <p class="font-semibold text-ink-900">{{ $assignment->completed_at?->format('d M Y H:i') ?? '—' }}</p>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('qa.exam.grading.store', $assignment) }}" class="space-y-4">
        @csrf

        @foreach ($form->questions as $question)
            @php
                $answer = $answers->get($question->id);
                $isEssay = $question->question_type_snapshot === 'essay';
                $selected = $answer?->answer['selected'] ?? (array) ($answer?->answer['value'] ?? []);
            @endphp

            <div class="card card-body">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                    <h2 class="text-sm font-semibold text-ink-900">
                        {{ $loop->iteration }}. {{ $question->question_snapshot }}
                    </h2>
                    <x-badge :variant="$isEssay ? 'amber' : 'brand'">
                        {{ $isEssay ? 'Essay' : 'Pilihan Ganda' }} &middot; maks {{ $question->point }}
                    </x-badge>
                </div>

                @if ($isEssay)
                    <div class="mb-3 whitespace-pre-line rounded-lg bg-ink-50 p-3 text-sm text-ink-700">{{ $answer?->answer['value'] ?? '—' }}</div>
                @else
                    <div class="mb-3 space-y-1">
                        @foreach ($question->options_snapshot ?? [] as $option)
                            @php
                                $chosen = in_array($option['label'], (array) $selected, true);
                                $correct = ! empty($option['is_correct']);
                            @endphp
                            <div class="flex items-center gap-2 text-sm">
                                <span class="w-6 font-semibold text-ink-500">{{ $option['label'] }}</span>
                                <span class="{{ $correct ? 'font-medium text-green-700' : 'text-ink-700' }}">{{ $option['text'] }}</span>
                                @if ($correct)<x-badge variant="green">Kunci</x-badge>@endif
                                @if ($chosen)<x-badge variant="brand">Dipilih</x-badge>@endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($answer)
                    <input type="hidden" name="scores[{{ $loop->index }}][answer_id]" value="{{ $answer->id }}">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Skor (maks {{ $question->point }})</label>
                            <input type="number" step="0.01" min="0" max="{{ $question->point }}"
                                   class="form-input w-full"
                                   name="scores[{{ $loop->index }}][score]"
                                   value="{{ old('scores.' . $loop->index . '.score', $answer->score) }}"
                                   @if (! $isEssay || ! $canGrade) readonly @endif>
                        </div>
                        <div>
                            <label class="form-label">Catatan penilai</label>
                            <input type="text" class="form-input w-full"
                                   name="scores[{{ $loop->index }}][grader_notes]"
                                   value="{{ old('scores.' . $loop->index . '.grader_notes', $answer->grader_notes) }}"
                                   placeholder="Opsional" @if (! $isEssay || ! $canGrade) readonly @endif>
                        </div>
                    </div>
                    @if (! $isEssay)
                        <p class="mt-1 text-xs text-ink-500">Dinilai otomatis berdasarkan kunci jawaban.</p>
                    @endif
                @else
                    <p class="text-sm text-ink-500">Soal tidak dijawab.</p>
                @endif
            </div>
        @endforeach

        <div class="flex justify-between">
            <a href="{{ route('qa.exam.grading.index') }}" class="btn-secondary">Kembali</a>
            @if ($canGrade)
                <button type="submit" class="btn-primary">Simpan Penilaian</button>
            @endif
        </div>
    </form>
@endsection
