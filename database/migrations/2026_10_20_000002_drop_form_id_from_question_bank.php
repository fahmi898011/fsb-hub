<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bank soal ujian kembali jadi kolam soal bersama (bukan milik satu form).
 *
 * AGENT: asisten | 2026-10-20 | hapus form_id dari question_bank | modul QA Assessment, bank soal
 *
 * Koreksi lanjutan: kepemilikan soal per form (`question_bank.form_id`) membuat
 * soal yang sudah ditulis di form A tidak bisa dipakai di form B. Yang benar:
 *   - `question_bank`  = kolam soal bersama, tidak tahu form mana pun
 *   - `exam_form_questions` = daftar soal milik sebuah form (banyak-ke-banyak)
 * Sebelum kolomnya dibuang, soal lama yang masih berinduk ke sebuah form
 * disalin dulu ke pivot supaya tidak hilang dari form tersebut.
 *
 * `down()` hanya mengembalikan kolomnya (nullable); kepemilikan per soal tidak
 * bisa direkonstruksi karena satu soal kini boleh dipakai banyak form.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('question_bank', 'form_id')) {
            return;
        }

        $this->copyOwnershipToPivot();

        Schema::table('question_bank', function (Blueprint $table) {
            $table->dropForeign(['form_id']);
            $table->dropIndex(['form_id', 'status']);
            $table->dropColumn('form_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('question_bank', 'form_id')) {
            return;
        }

        Schema::table('question_bank', function (Blueprint $table) {
            $table->foreignId('form_id')
                ->nullable()
                ->after('id')
                ->constrained('exam_forms')
                ->cascadeOnDelete();

            $table->index(['form_id', 'status']);
        });
    }

    /**
     * Salin `question_bank.form_id` ke `exam_form_questions` bila belum ada.
     * Urutan ditaruh paling akhir pada form masing-masing.
     */
    private function copyOwnershipToPivot(): void
    {
        $questions = DB::table('question_bank')
            ->whereNotNull('form_id')
            ->get(['id', 'form_id', 'question_type', 'question', 'options', 'point']);

        if ($questions->isEmpty()) {
            return;
        }

        $sortOrders = DB::table('exam_form_questions')
            ->select('form_id', DB::raw('MAX(sort_order) as max_sort'))
            ->groupBy('form_id')
            ->pluck('max_sort', 'form_id');

        $now = now();
        $rows = [];

        foreach ($questions as $question) {
            $sortOrders[$question->form_id] = (int) ($sortOrders[$question->form_id] ?? 0) + 1;

            $rows[] = [
                'form_id' => $question->form_id,
                'question_bank_id' => $question->id,
                'sort_order' => $sortOrders[$question->form_id],
                'point' => $question->point ?: 1,
                'question_type_snapshot' => $question->question_type,
                'question_snapshot' => $question->question,
                'options_snapshot' => $question->options,
                'point_snapshot' => $question->point ?: 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('exam_form_questions')->insertOrIgnore($rows);
    }
};
