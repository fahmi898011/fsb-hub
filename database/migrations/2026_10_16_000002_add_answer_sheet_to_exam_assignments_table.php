<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran lembar jawaban kertas pada assignment ujian.
 *
 * AGENT: asisten | 2026-10-16 | buat berkas baru | modul Quiz -> Ujian (kertas)
 *
 * Agent tidak selalu mengerjakan lewat layar: pada praktiknya jawaban ditulis di
 * kertas lalu QA memfoto/memindai lembar itu. Sistem tetap perlu menyimpan
 * berkasnya supaya (1) QA dapat menilai sambil melihat jawaban asli, dan
 * (2) hasil penilaian dapat dipertanggungjawabkan bila dipersoalkan.
 *
 * Berkas disimpan di disk privat (`local`), bukan `public`: lembar jawaban berisi
 * data pribadi karyawan dan tidak boleh bisa diakses hanya dengan menebak URL.
 * Aksesnya melewati route ber-otorisasi.
 *
 * `after()` sengaja tidak dipakai: ia hanya berlaku di MySQL, sementara test
 * berjalan di SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_assignments', function (Blueprint $table) {
            $table->string('answer_sheet_path')->nullable();
            $table->foreignId('answer_sheet_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answer_sheet_uploaded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('exam_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('answer_sheet_uploaded_by');
            $table->dropColumn(['answer_sheet_path', 'answer_sheet_uploaded_at']);
        });
    }
};
