<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item penilaian QA Sampling (PRD Employee KPI/QA §9/§11/§16/§31).
 *
 * AGENT: asisten | 2026-10-03 | buat berkas baru | modul KPI, fase 2 QA sampling
 *
 * Satu baris = satu parameter yang dinilai pada satu assessment.
 *
 * Mengapa kolomnya banyak yang berakhiran `_snapshot`: inilah wujud nyata
 * aturan "Weight Snapshot" (PRD §11) sekaligus cara skor dihitung ULANG (§10)
 * tanpa menyentuh master. Karena `answer_polarity_snapshot` dan
 * `parameter_type_snapshot` ikut disalin, mesin skor tetap benar walaupun
 * master parameter diubah setelah assessment dibuat - termasuk bila satu
 * versi dinonaktifkan lalu disunting lagi.
 *
 * `penalty` disimpan hasil hitung per item (bukan dihitung ulang tiap kali
 * dibaca) supaya laporan Fase 3 dapat menjumlahkannya langsung, sementara
 * `is_violation` menandai item yang "kena" aturan - dilihat dari arah jawaban
 * (`answer_polarity`), bukan dari teks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_sampling_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('qa_sampling_assessment_id')
                ->constrained('qa_sampling_assessments')
                ->cascadeOnDelete();

            // Parameter asal. `nullOnDelete` supaya penghapusan master (yang
            // seharusnya dihindari, PRD §46) tidak ikut menghapus riwayat.
            $table->foreignId('scorecard_parameter_id')
                ->nullable()
                ->constrained('scorecard_parameters')
                ->nullOnDelete();

            // Snapshot dasar (PRD §11).
            $table->string('category_name_snapshot');
            $table->string('parameter_name_snapshot');
            $table->decimal('weight_snapshot', 5, 2)->default(0);

            // Snapshot aturan, supaya skor historis tidak bergantung master.
            $table->enum('parameter_type_snapshot', ['normal', 'critical', 'zero_tolerance'])->default('normal');
            $table->enum('ztp_effect_snapshot', ['weight_only', 'zero_score'])->default('zero_score');
            $table->enum('answer_polarity_snapshot', ['normal', 'inverted'])->default('normal');
            $table->boolean('is_required_snapshot')->default(true);

            // Jawaban: yes/no/na. Null berarti belum dinilai.
            $table->enum('answer', ['yes', 'no', 'na'])->nullable();

            // Hasil hitung: apakah item ini "kena" aturan, dan besar penaltynya.
            // ZTP `zero_score` yang tersentuh ditandai `is_violation` tetapi
            // `penalty` = 0, sebab efeknya memaksa skor 0 (bukan pengurangan).
            $table->boolean('is_violation')->default(false);
            $table->decimal('penalty', 5, 2)->default(0);

            $table->text('comment')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['qa_sampling_assessment_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_sampling_items');
    }
};
