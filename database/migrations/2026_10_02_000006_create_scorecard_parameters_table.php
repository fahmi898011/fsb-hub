<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parameter Scorecard (PRD Employee KPI/QA §8/§13/§30).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Inilah master aturan penilaian. Tiga keputusan penting dibekukan di sini:
 *
 * 1. `weight` disimpan sebagai PERSEN (10.00 = 10%), mengikuti isi form Excel.
 *    Formula skor (PRD §9) adalah:
 *        Final = 100 - SUM(weight parameter yang kena penalty)
 *    sehingga total bobot normal sebuah scorecard harus 100% (PRD §37).
 *
 * 2. `parameter_type` (+ `ztp_effect`) memisahkan Zero Tolerance dari
 *    parameter normal TANPA menebak dari nama parameter. Ini wajib: di form
 *    Excel, baris "Zero Tolerance Policy" ber-bobot 0 dan bermakna TERBALIK
 *    (jawaban "NO" = tidak ada pelanggaran = tanpa penalty), sehingga logika
 *    ZTP tidak boleh dicocokkan lewat teks nama.
 *
 * 3. `answer_polarity` menyimpan arah makna jawaban secara eksplisit:
 *    - `normal`   : NO = penalty (parameter biasa).
 *    - `inverted` : YES = penalty (mis. ZTP "apakah terjadi pelanggaran?").
 *
 * `evaluation_type` mengatur pilihan jawaban (YES/NO vs YES/NO/NA), dan
 * `is_required` menandai parameter wajib dinilai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scorecard_parameters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('scorecard_category_id')->constrained('scorecard_categories')->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Bobot dalam persen: 10.00 berarti 10%.
            $table->decimal('weight', 5, 2)->default(0);

            // Pilihan jawaban yang tersedia di form QA.
            $table->enum('evaluation_type', ['yes_no', 'yes_no_na'])->default('yes_no');

            // Jenis parameter (PRD §13).
            $table->enum('parameter_type', ['normal', 'critical', 'zero_tolerance'])->default('normal');

            // Efek khusus ZTP (hanya relevan bila parameter_type = zero_tolerance):
            // - weight_only : penalty sebesar `weight` parameter ini.
            // - zero_score  : hukuman memaksa skor akhir menjadi 0.
            $table->enum('ztp_effect', ['weight_only', 'zero_score'])->default('zero_score');

            // Arah makna jawaban (lihat docblock).
            $table->enum('answer_polarity', ['normal', 'inverted'])->default('normal');

            $table->text('guideline')->nullable();
            $table->text('penalty_rule')->nullable();
            $table->boolean('is_required')->default(true);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['scorecard_category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scorecard_parameters');
    }
};
