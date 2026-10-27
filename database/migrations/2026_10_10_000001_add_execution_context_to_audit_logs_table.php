<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak eksekusi pada `audit_logs` (PRD §16).
 *
 * AGENT: asisten | 2026-10-10 | tambah kolom jejak eksekusi | modul Audit Log, §11.15
 *
 * Sebelum ini `audit_logs` hanya menjawab APA yang berubah, bukan BAGAIMANA
 * perubahan itu dipicu (permintaan HTTP mana, rute apa, berhasil atau tidak)
 * dan tidak menyimpan label manusiawi entitas yang disentuh — sehingga baris
 * seperti `published | InformationItem #10 | {"status":"published"}` tidak
 * bisa dipertanggungjawabkan tanpa membuka tabel lain.
 *
 * Kolom di sini MELENGKAPI kolom lama (tidak ada yang diubah/dihapus), jadi
 * seluruh pemanggil `AuditLogService::log()` yang sudah ada tetap bekerja
 * tanpa perubahan.
 *
 * Catatan: `after()` sengaja tidak dipakai agar migration ini juga sah di
 * SQLite (dipakai test suite) — urutan kolom fisik tidak penting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Identitas satu permintaan HTTP. Dipakai `AuditHttpRequest` untuk
            // melengkapi `status_code` setelah respons diketahui (nilainya
            // belum ada saat controller menulis log).
            $table->char('request_id', 36)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('route_name', 191)->nullable();
            $table->text('url')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();

            // Klasifikasi entri: modul (dari prefiks aksi), kategori, tingkat
            // kepentingan, dan hasil eksekusi.
            $table->string('module', 50)->nullable();
            $table->string('category', 30)->nullable();
            $table->string('severity', 20)->nullable();
            $table->string('result', 20)->nullable();

            // Ringkasan manusiawi: satu kalimat + label entitas + kolom yang
            // benar-benar berubah.
            $table->text('description')->nullable();
            $table->string('subject_label', 191)->nullable();
            $table->json('changed_fields')->nullable();

            // Potret pelaku saat kejadian. Bertahan walau akunnya dihapus,
            // karena `user_id` memakai `nullOnDelete` — tanpa potret ini
            // pelaku yang dihapus berubah menjadi "Sistem" di daftar audit.
            $table->string('actor_name', 191)->nullable();
            $table->string('actor_email', 191)->nullable();
            $table->string('actor_role', 50)->nullable();

            $table->index('request_id');
            $table->index('module');
            $table->index('severity');
            $table->index('result');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['request_id']);
            $table->dropIndex(['module']);
            $table->dropIndex(['severity']);
            $table->dropIndex(['result']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn([
                'request_id',
                'method',
                'route_name',
                'url',
                'status_code',
                'module',
                'category',
                'severity',
                'result',
                'description',
                'subject_label',
                'changed_fields',
                'actor_name',
                'actor_email',
                'actor_role',
            ]);
        });
    }
};
