<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soal berinduk ke form (koreksi model data bank soal ujian QA).
 *
 * AGENT: asisten | 2026-10-20 | tambah kolom form_id | modul QA Assessment, bank soal
 *
 * Sebelum ini `question_bank` adalah kolam soal lepas: satu baris = satu soal,
 * tanpa tahu soal itu dipakai form mana. Akibatnya QA menulis soal di halaman
 * bank, lalu harus menambahkannya satu per satu ke form lewat dropdown yang
 * memuat SELURUH soal aktif — dan soal milik form A ikut ditawarkan ke form B.
 *
 * Patokan yang dipakai pengguna: "1 form banyak soal, patokannya form". Karena
 * itu soal yang ditulis di dalam sebuah form diberi `form_id`:
 *   - `form_id` terisi  -> soal milik form itu saja (tidak ditawarkan ke form lain)
 *   - `form_id` NULL    -> soal bank umum, boleh dipakai lintas form
 *
 * Nullable supaya soal lama (dan soal yang sengaja dibagikan) tetap sah.
 * Hapus form => soalnya ikut terhapus (cascade), sama seperti pivot
 * `exam_form_questions` yang sudah cascade ke form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_bank', function (Blueprint $table) {
            $table->foreignId('form_id')
                ->nullable()
                ->after('id')
                ->constrained('exam_forms')
                ->cascadeOnDelete();

            $table->index(['form_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('question_bank', function (Blueprint $table) {
            $table->dropForeign(['form_id']);
            $table->dropIndex(['form_id', 'status']);
            $table->dropColumn('form_id');
        });
    }
};
