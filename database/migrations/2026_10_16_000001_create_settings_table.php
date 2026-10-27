<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar fitur (feature toggle) tingkat aplikasi.
 *
 * AGENT: asisten | 2026-10-16 | buat berkas baru | modul Pengaturan Fitur
 *
 * Kebutuhan asalnya datang dari modul Quiz/Ujian: sebagian perilaku harus bisa
 * dinyalakan/dimatikan TANPA deploy (mis. "agent boleh melihat foto lembar
 * jawabannya", "quiz pilihan ganda lama disembunyikan"). Menyimpannya di
 * `config/` berarti setiap perubahan harus lewat rilis ulang, dan itu terlalu
 * mahal untuk keputusan operasional harian.
 *
 * Nilai disimpan sebagai teks + kolom `type` supaya tidak perlu tabel terpisah
 * per jenis. Baris default di-INSERT di migration ini (bukan hanya di seeder)
 * agar instalasi yang sudah berjalan langsung memiliki saklar yang bisa dipakai
 * tanpa menjalankan seeder ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group', 40)->default('umum');
            $table->string('label');
            $table->text('description')->nullable();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('boolean');
            $table->timestamps();
        });

        $now = now();

        DB::table('settings')->insert([
            [
                'key' => 'exam.digital_mode',
                'group' => 'ujian',
                'label' => 'Mode pengerjaan digital',
                'description' => 'Agent boleh mengerjakan ujian lewat form di layar. Bila dimatikan, ujian hanya dikerjakan di kertas.',
                'value' => '1',
                'type' => 'boolean',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'exam.paper_mode',
                'group' => 'ujian',
                'label' => 'Unggah lembar jawaban kertas',
                'description' => 'QA boleh mengunggah foto/scan lembar jawaban agent pada halaman penilaian.',
                'value' => '1',
                'type' => 'boolean',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'exam.agent_can_view_sheet',
                'group' => 'ujian',
                'label' => 'Agent melihat lembar jawabannya',
                'description' => 'Agent boleh membuka foto/scan lembar jawabannya sendiri (hanya baca). Default mati agar jawaban tidak tersebar.',
                'value' => '0',
                'type' => 'boolean',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'quiz.multiple_choice',
                'group' => 'quiz',
                'label' => 'Tampilkan Quiz pilihan ganda (arsip)',
                'description' => 'Menu Quiz pilihan ganda lama. Data tidak dihapus; hanya disembunyikan saat saklar ini mati.',
                'value' => '0',
                'type' => 'boolean',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
