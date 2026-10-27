<?php

namespace App\Services;

use App\Models\QuestionBank;
use App\Models\QuizAttempt;
use App\Models\QuizTemplate;
use App\Models\QuizVersion;
use App\Models\QuizVersionQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pengelolaan quiz: pembuatan versi, pemilihan soal, publish, dan mulai attempt
 * (PRD Employee KPI/QA §22-§24/§27).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Berkas ini memegang tiga aturan yang membuat quiz dapat dipercaya:
 *
 * 1. SNAPSHOT SOAL (PRD §23). Saat versi dibuat, teks soal & poin DISALIN ke
 *    `quiz_version_questions`. Mulai titik itu versi mandiri: bank soal boleh
 *    berubah, quiz lama tetap sama.
 *
 * 2. HANYA SATU VERSI AKTIF per template. Menerbitkan versi baru otomatis
 *    menonaktifkan versi sebelumnya, supaya tidak ada dua "versi berlaku" yang
 *    membingungkan karyawan.
 *
 * 3. VERSI YANG SUDAH DIKERJAKAN TERKUNCI (PRD §23). Bila sudah ada attempt,
 *    versi tidak boleh disusun ulang — kalau tidak, hasil orang yang sudah
 *    mengerjakan bisa berubah maknanya.
 */
class QuizService
{
    public function __construct(private readonly QuizScoringService $scoring) {}

    /**
     * Buat versi baru untuk sebuah template.
     *
     * @param  array{question_ids?: list<int>, version?: int, passing_score?: float, time_limit?: ?int, notes?: ?string, effective_from?: ?string}  $data
     */
    public function createVersion(QuizTemplate $template, array $data, User $actor): QuizVersion
    {
        return DB::transaction(function () use ($template, $data, $actor) {
            $versionNumber = $data['version'] ?? ((int) $template->versions()->max('version') + 1);

            /** @var QuizVersion $version */
            $version = $template->versions()->create([
                'version' => $versionNumber,
                'question_count' => 0,
                'passing_score' => $data['passing_score'] ?? $template->passing_score,
                'time_limit' => $data['time_limit'] ?? $template->time_limit,
                'effective_from' => $data['effective_from'] ?? now()->toDateString(),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncQuestions($version, $data['question_ids'] ?? []);

            return $version->fresh();
        });
    }

    /**
     * Pilih soal untuk versi sesuai `selection_method` template.
     *
     * @return list<int> id soal bank yang terpilih
     */
    public function selectQuestionIds(QuizTemplate $template): array
    {
        $count = max(1, (int) $template->question_count);

        $query = QuestionBank::query()
            ->active()
            ->when($template->department_id, fn ($q, $id) => $q->where(function ($q) use ($id) {
                // Soal umum (tanpa divisi) boleh dipakai divisi mana pun.
                $q->whereNull('department_id')->orWhere('department_id', $id);
            }));

        if ($template->selection_method === 'random') {
            return $query->inRandomOrder()->limit($count)->pluck('id')->all();
        }

        if ($template->selection_method === 'category') {
            $byCategory = (clone $query)->whereNotNull('question_category_id')
                ->inRandomOrder()->get(['id', 'question_category_id'])->groupBy('question_category_id');
            $byCategory = $byCategory->shuffle();
            $selected = [];
            while (count($selected) < $count && $byCategory->isNotEmpty()) {
                foreach ($byCategory as $categoryId => $questions) {
                    if ($questions->isEmpty()) {
                        $byCategory->forget($categoryId);
                        continue;
                    }
                    $selected[] = (int) $questions->shift()->id;
                    if (count($selected) >= $count) {
                        break;
                    }
                }
            }

            return $selected;
        }

        // Manual selection without explicit IDs is an ordered initial proposal
        // which QA may adjust before publishing.
        return $query->orderBy('id')->limit($count)->pluck('id')->all();
    }

    /**
     * Salin daftar soal ke versi (mengganti yang lama), membekukan teks + poin.
     *
     * @param  list<int>  $questionIds
     */
    public function syncQuestions(QuizVersion $version, array $questionIds): void
    {
        if ($version->isLocked()) {
            throw ValidationException::withMessages([
                'questions' => 'Versi ini sudah dikerjakan karyawan, susunan soalnya tidak boleh diubah.',
            ]);
        }

        $version->questions()->delete();

        $questions = QuestionBank::query()
            ->whereIn('id', $questionIds)
            ->get()
            ->keyBy('id');

        $rows = [];
        $order = 0;
        $now = now();

        foreach (array_values($questionIds) as $questionId) {
            $question = $questions->get((int) $questionId);

            if (! $question) {
                continue;
            }

            $rows[] = [
                'quiz_version_id' => $version->getKey(),
                'question_bank_id' => $question->getKey(),
                'question_snapshot' => $question->question,
                'question_type_snapshot' => $question->question_type,
                'options_snapshot' => json_encode($question->options->map(fn ($option) => [
                    'id' => (int) $option->id,
                    'label' => $option->option_label,
                    'text' => $option->option_text,
                    'is_correct' => (bool) $option->is_correct,
                ])->values()->all()),
                'point_snapshot' => $question->point,
                'sort_order' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            QuizVersionQuestion::insert($rows);
        }

        $version->update(['question_count' => count($rows)]);
    }

    /**
     * Terbitkan versi: status menjadi `active`, versi lain dinonaktifkan.
     */
    public function publish(QuizVersion $version): QuizVersion
    {
        if ($version->questions()->count() === 0) {
            throw ValidationException::withMessages([
                'questions' => 'Versi belum memiliki soal, tidak dapat diterbitkan.',
            ]);
        }

        return DB::transaction(function () use ($version) {
            $version->template->versions()
                ->whereKeyNot($version->getKey())
                ->where('status', 'active')
                ->update(['status' => 'inactive', 'updated_at' => now()]);

            $version->update([
                'status' => 'active',
                'published_at' => now(),
            ]);

            return $version->fresh();
        });
    }

    /**
     * Mulai pengerjaan quiz untuk seorang karyawan.
     *
     * Mengembalikan attempt yang sudah ada bila masih `pending` (belum submit),
     * supaya karyawan yang menutup tab tidak kehilangan sesinya.
     */
    public function startAttempt(QuizVersion $version, User $employee): QuizAttempt
    {
        return DB::transaction(function () use ($version, $employee) {
            $existing = QuizAttempt::query()
                ->where('employee_id', $employee->id)
                ->where('quiz_version_id', $version->getKey())
                ->whereNull('completed_at')
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            return QuizAttempt::create([
                'employee_id' => $employee->id,
                'quiz_version_id' => $version->getKey(),
                'quiz_template_id' => $version->quiz_template_id,
                'quiz_name_snapshot' => $version->template?->name ?? 'Quiz',
                'version_number_snapshot' => $version->version,
                'passing_score_snapshot' => $version->passing_score,
                'started_at' => now(),
                'total_questions' => $version->questions()->count(),
                'result' => 'pending',
            ]);
        });
    }

    /**
     * Submit jawaban: simpan, nilai, finalisasi.
     *
     * @param  array<int, list<int>>  $selectedByVersionQuestionId
     */
    public function submit(QuizAttempt $attempt, array $selectedByVersionQuestionId): QuizAttempt
    {
        if ($attempt->isCompleted()) {
            throw ValidationException::withMessages([
                'attempt' => 'Quiz ini sudah dikumpulkan.',
            ]);
        }

        return DB::transaction(function () use ($attempt, $selectedByVersionQuestionId) {
            $this->scoring->recordAnswers($attempt, $selectedByVersionQuestionId);

            return $this->scoring->finalize($attempt);
        });
    }
}
