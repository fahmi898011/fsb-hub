<?php

namespace App\Services;

use App\Models\QaExamQuestionBank;
use Illuminate\Validation\ValidationException;

/**
 * Penulis soal massal ujian tertulis QA.
 *
 * Satu halaman isian boleh berisi BANYAK soal: baris yang terisi disimpan
 * sekaligus sebagai soal bank, lalu (opsional) ditempel ke sebuah form ujian.
 * Dipakai dua tempat supaya aturannya tidak bercabang:
 *   - `qa.exam.forms.questions.bulk`  (tulis soal di halaman form ujian), dan
 *   - `qa.exam.question-bank.store`   (tulis soal di halaman bank soal).
 *
 * AGENT: asisten | 2026-10-20 | satu penulis soal massal | modul QA Assessment
 */
class QaQuestionComposer
{
    /** Batas jumlah baris sekali simpan, supaya request tidak membengkak. */
    public const MAX_ROWS = 50;

    /**
     * Buang baris yang pertanyaannya kosong.
     *
     * Halaman isian selalu menyiapkan beberapa baris kosong siap pakai, jadi
     * baris kosong bukan error: QA cukup mengisi yang perlu saja.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['question'] ?? '')) !== '')
            ->values()
            ->all();
    }

    /**
     * Aturan validasi baris soal (dipakai bersama kedua halaman).
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'questions' => 'required|array|min:1|max:'.self::MAX_ROWS,
            'questions.*.question' => 'required|string',
            'questions.*.question_type' => 'required|in:essay,multiple_choice',
            'questions.*.point' => 'nullable|integer|min:0|max:1000',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*' => 'nullable|string',
            'questions.*.correct' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Pesan validasi berbahasa Indonesia untuk baris soal.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'questions.required' => 'Isi minimal satu soal sebelum menyimpan.',
            'questions.min' => 'Isi minimal satu soal sebelum menyimpan.',
            'questions.max' => 'Maksimal '.self::MAX_ROWS.' soal sekali simpan.',
        ];
    }

    /**
     * Simpan semua baris terisi menjadi soal bank.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, QaExamQuestionBank>
     */
    public static function createMany(array $rows, ?int $categoryId = null, string $status = 'active'): array
    {
        $created = [];

        foreach ($rows as $index => $row) {
            $created[] = self::createQuestion($index, $row, $categoryId, $status);
        }

        return $created;
    }

    /**
     * Simpan satu baris isian menjadi soal bank.
     *
     * @param  int  $index  posisi baris pada form isian (dipakai di kunci error)
     * @param  array<string, mixed>  $row
     */
    public static function createQuestion(int $index, array $row, ?int $categoryId = null, string $status = 'active'): QaExamQuestionBank
    {
        $type = $row['question_type'] ?? QaExamQuestionBank::TYPE_ESSAY;

        $options = $type === QaExamQuestionBank::TYPE_MULTIPLE_CHOICE
            ? self::buildOptions($index, $row)
            : null;

        return QaExamQuestionBank::create([
            'category_id' => $categoryId,
            'question_type' => $type,
            'question' => trim((string) $row['question']),
            'options' => $options,
            'point' => $row['point'] ?? 1,
            'status' => $status,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Susun opsi pilihan ganda (label A, B, C, ...) dari baris isian.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, array{label: string, text: string, is_correct: bool}>
     */
    private static function buildOptions(int $index, array $row): array
    {
        $labels = range('A', 'Z');
        $correct = (int) ($row['correct'] ?? 0);

        $texts = collect($row['options'] ?? [])
            ->map(fn ($text) => trim((string) $text))
            ->values();

        if ($texts->filter(fn ($text) => $text !== '')->count() < 2) {
            throw ValidationException::withMessages([
                "questions.{$index}.options" => 'Soal pilihan ganda butuh minimal 2 opsi terisi.',
            ]);
        }

        if (($texts[$correct] ?? '') === '') {
            throw ValidationException::withMessages([
                "questions.{$index}.correct" => 'Tandai satu opsi sebagai jawaban benar.',
            ]);
        }

        $options = [];
        $position = 0;

        foreach ($texts as $optionIndex => $text) {
            if ($text === '') {
                continue;
            }

            $options[] = [
                'label' => $labels[$position] ?? '?',
                'text' => $text,
                'is_correct' => $optionIndex === $correct,
            ];

            $position++;
        }

        return $options;
    }
}
