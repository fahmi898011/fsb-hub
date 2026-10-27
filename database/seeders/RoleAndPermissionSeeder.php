<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Informasi
            'information.view',
            'information.create',
            'information.update',
            'information.delete',
            'information.publish',
            'information.archive',
            'information.version',
            // Kategori
            'category.view',
            'category.create',
            'category.update',
            'category.delete',
            // User
            'user.view',
            'user.create',
            'user.update',
            'user.disable',
            'user.import',
            // Tag
            'tag.view',
            'tag.update',
            'tag.delete',
            // Divisi (struktur organisasi)
            'department.view',
            'department.create',
            'department.update',
            'department.delete',
            // Tim (struktur organisasi)
            'team.view',
            'team.create',
            'team.update',
            'team.delete',
            // Tiket IT Support
            'ticket.view',
            'ticket.update',
            'ticket.assign',
            // Chat internal (PRD Chat §10).
            //
            // Hanya izin PENGELOLAAN yang didaftarkan di sini. Hak "membaca
            // percakapan yang diikuti" tidak memakai permission apa pun,
            // karena keanggotaan sudah menjadi penjaganya di
            // ConversationPolicy — sebuah permission seperti `chat.view`
            // justru berisiko memberi akses ke percakapan orang lain.
            //
            // `message.update` dan `message.delete` sengaja DISIAPKAN tetapi
            // tidak diberikan ke role mana pun: fitur edit/hapus pesan masih
            // ditunda (PRD §10/§15).
            'channel.create',
            'channel.update',
            'channel.delete',
            'channel.manage_members',
            // KPI & QA Scorecard (PRD Employee KPI/QA §35).
            // AGENT: asisten | 2026-10-02 | tambah permission | modul KPI, fase 1 master
            //
            // NB: master channel KPI memakai awalan `kpi_channel.` agar tidak
            // bertabrakan dengan `channel.*` milik modul Chat.
            'kpi.view',
            'kpi.create',
            'kpi.update',
            'kpi.delete',
            'kpi_channel.view',
            'kpi_channel.create',
            'kpi_channel.update',
            'kpi_channel.delete',
            'scorecard.view',
            'scorecard.create',
            'scorecard.update',
            'scorecard.delete',
            // QA Sampling — penilaian sample (PRD Employee KPI/QA §14-§16).
            // AGENT: asisten | 2026-10-03 | tambah permission QA sampling | modul KPI, fase 2 QA sampling
            //
            // Prefiks `qa_sampling.` (bukan `qa_assessment.`) dipilih karena
            // `qa_assessment.*` sudah disiapkan untuk modul penilaian
            // kualitatif "PRD QA Assessment" §10 — dua modul berbeda yang
            // tidak boleh berbagi nama izin.
            'qa_sampling.view',
            'qa_sampling.create',
            'qa_sampling.update',
            'qa_sampling.delete',
            'qa_sampling.submit',
            'qa_sampling.review',
            'qa_sampling.lock',
            'qa_sampling.cancel',
            // Laporan & analitik lintas karyawan (PRD Employee KPI/QA §39-§42).
            // AGENT: asisten | 2026-10-04 | tambah permission laporan QA sampling | modul KPI, fase 3 QA reporting
            //
            // Dipisahkan dari `qa_sampling.view` dengan sengaja. `qa_sampling.view`
            // juga dipakai KARYAWAN (lewat policy, untuk membuka assessment
            // miliknya). Bila laporan memakai izin yang sama, tidak ada cara
            // menolak karyawan membuka laporan yang berisi data orang lain
            // tanpa sekaligus mencabut haknya melihat hasil sendiri.
            'qa_sampling.report.view',
            // Target Sampling — konfigurasi kebijakan (PRD §17/§18).
            // Dikelola Admin; QA/HR/Supervisor hanya `view`.
            'qa_sampling_target.view',
            'qa_sampling_target.create',
            'qa_sampling_target.update',
            'qa_sampling_target.delete',
            // QA Assessment — penilaian kualitatif + ujian tertulis (PRD QA Assessment §10).
            // AGENT: asisten | 2026-10-08 | tambah permission QA Assessment | modul QA Assessment
            // Sub-bagian A: penilaian kualitatif (kategori, assessment, items)
            'qa.assessment.category.view',
            'qa.assessment.category.create',
            'qa.assessment.category.update',
            'qa.assessment.category.delete',
            'qa.assessment.view',
            'qa.assessment.create',
            'qa.assessment.update',
            'qa.assessment.delete',
            'qa.assessment.publish',
            // Sub-bagian B: ujian tertulis (question_bank, exam_forms, assignments, answers)
            'qa.exam.question-bank.view',
            'qa.exam.question-bank.create',
            'qa.exam.question-bank.update',
            'qa.exam.question-bank.delete',
            'qa.exam.form.view',
            'qa.exam.form.create',
            'qa.exam.form.update',
            'qa.exam.form.delete',
            'qa.exam.form.publish',
            'qa.exam.assignment.view',
            'qa.exam.assignment.create',
            'qa.exam.assignment.update',
            'qa.exam.assignment.delete',
            'qa.exam.assignment.grade',
            'qa.exam.assignment.publish',
            'qa.exam.answer.view',
            'qa.exam.answer.create',
            'qa.exam.answer.update',
            'qa.exam.answer.delete',
            // Sub-bagian C: periode & penugasan agent
            'qa.period.view',
            'qa.period.create',
            'qa.period.update',
            'qa.period.delete',
            'qa.agent-assignment.view',
            'qa.agent-assignment.create',
            'qa.agent-assignment.update',
            'qa.agent-assignment.delete',
            // Quiz / Knowledge Assessment (PRD Employee KPI/QA §20-§27).
            // AGENT: asisten | 2026-10-05 | tambah permission quiz | modul Quiz, fase 4 quiz
            //
            // `question_bank.*` = bank soal (aset pengetahuan QA).
            // `quiz.*`          = template & versi quiz (konfigurasi penilaian).
            // `quiz.publish`    = menerbitkan versi agar dapat dikerjakan.
            // `quiz.take`       = mengerjakan quiz (khusus karyawan).
            // `quiz_result.view`= melihat hasil lintas karyawan (laporan).
            //
            // `quiz.take` dan `quiz_result.view` sengaja DIPISAH: karyawan boleh
            // mengerjakan dan melihat hasil sendiri, sedangkan melihat hasil
            // semua orang hanya untuk pihak yang berhak (QA/Supervisor/HR).
            'question_bank.view',
            'question_bank.create',
            'question_bank.update',
            'question_bank.delete',
            'quiz.view',
            'quiz.create',
            'quiz.update',
            'quiz.delete',
            'quiz.publish',
            'quiz.take',
            'quiz_result.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Admin: Application Management (bukan content creator).
        // PRD §1/§16 — Admin mengatur wadah: Master Informasi, User, Role, System.
        // Admin hanya mempertahankan 'information.view' untuk kebutuhan teknis/audit.
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
            // Master Informasi (kategori) — tanggung jawab Admin
            'category.view',
            'category.create',
            'category.update',
            'category.delete',
            // User Management
            'user.view',
            'user.create',
            'user.update',
            'user.disable',
            'user.import',
            // Struktur organisasi — Admin mengatur wadah, jadi punya hak penuh
            'department.view',
            'department.create',
            'department.update',
            'department.delete',
            'team.view',
            'team.create',
            'team.update',
            'team.delete',
            // Tiket — Admin mengatur wadah aplikasi, jadi boleh melihat dan
            // membantu menangani antrean bila tim IT belum tersedia.
            'ticket.view',
            'ticket.update',
            'ticket.assign',
            // Chat: Admin mengelola kanal — membuat, mengubah, mengarsipkan,
            // dan mengatur anggota. Admin TIDAK mendapat cara membaca DM
            // orang lain: itu bukan permission, melainkan keanggotaan.
            'channel.create',
            'channel.update',
            'channel.delete',
            'channel.manage_members',
            // KPI & QA Scorecard (PRD Employee KPI/QA §35) — Admin adalah
            // pemilik master aturan penilaian: KPI, channel KPI, scorecard,
            // versi, kategori, dan bobot parameter.
            // AGENT: asisten | 2026-10-02 | tambah permission Admin | modul KPI, fase 1 master
            'kpi.view',
            'kpi.create',
            'kpi.update',
            'kpi.delete',
            'kpi_channel.view',
            'kpi_channel.create',
            'kpi_channel.update',
            'kpi_channel.delete',
            'scorecard.view',
            'scorecard.create',
            'scorecard.update',
            'scorecard.delete',
            // QA Sampling (PRD §35): Admin mengatur penugasan dan ikut melihat
            // hasil, tetapi TIDAK menilai (tanpa `.create`/`.update`). Ini
            // sejalan dengan pemisahan wewenang Admin vs QA di fase 1.
            // AGENT: asisten | 2026-10-03 | tambah permission Admin QA sampling | modul KPI, fase 2 QA sampling
            'qa_sampling.view',
            'qa_sampling.review',
            'qa_sampling.lock',
            'qa_sampling.cancel',
            // Laporan + Target Sampling (PRD §39-§42/§17-§18). Admin memegang
            // KONFIGURASI kebijakan target; QA/HR/Supervisor cukup membacanya.
            // AGENT: asisten | 2026-10-04 | tambah permission Admin laporan & target | modul KPI, fase 3 QA reporting
            'qa_sampling.report.view',
            'qa_sampling_target.view',
            'qa_sampling_target.create',
            'qa_sampling_target.update',
            'qa_sampling_target.delete',
            // Quiz: Admin mengatur WADAH dan audit, bukan isi soal. Ia dapat
            // MELIHAT bank soal & hasil, tetapi tidak menyusun soal/menerbitkan
            // quiz — itu pekerjaan QA (PRD §35: pemisahan wadah vs isi).
            // AGENT: asisten | 2026-10-05 | tambah permission Admin quiz | modul Quiz, fase 4 quiz
            'question_bank.view',
            'quiz.view',
            'quiz_result.view',
            // QA Assessment: Admin mengatur wadah & audit, melihat laporan.
            // QA yang menilai & menyusun soal. Admin tidak menilai/menyusun.
            'qa.assessment.category.view',
            'qa.assessment.view',
            'qa.assessment.publish',
            'qa.exam.question-bank.view',
            'qa.exam.form.view',
            'qa.exam.assignment.view',
            'qa.exam.assignment.publish',
            'qa.exam.answer.view',
            'qa.period.view',
            'qa.period.create',
            'qa.period.update',
            'qa.period.delete',
            'qa.agent-assignment.view',
            'qa.agent-assignment.create',
            'qa.agent-assignment.update',
            'qa.agent-assignment.delete',
            // Read-only atas informasi, agar dashboard/audit tetap berfungsi
            'information.view',
        ]);

        // QA: Content Management (mengisi wadah yang dibuat Admin).
        // QA memakai kategori yang dibuat Admin (read-only: category.view).
        $qa = Role::firstOrCreate(['name' => 'qa', 'guard_name' => 'web']);
        $qa->syncPermissions([
            'information.view',
            'information.create',
            'information.update',
            'information.publish',
            'information.archive',
            'information.version',
            'category.view',
            'tag.view',
            'tag.update',
            'tag.delete',
            // Chat: QA ikut mengelola kanal (PRD §6), tetapi TIDAK dapat
            // mengarsipkan kanal — arsip adalah keputusan Admin saja.
            'channel.create',
            'channel.update',
            'channel.manage_members',
            // KPI: QA hanya MEMAKAI master (memilih scorecard saat menilai),
            // TIDAK boleh mengubah bobot/parameter. Pemisahan wewenang ini
            // melindungi integritas skor historis (PRD §35).
            // AGENT: asisten | 2026-10-02 | tambah permission QA (read-only master) | modul KPI, fase 1 master
            'kpi.view',
            'kpi_channel.view',
            'scorecard.view',
            // QA Sampling (PRD §35): inilah pekerjaan inti QA - menilai sample.
            // QA boleh membuat, mengisi, mengirim, dan (sesuai §35 "Review
            // assessment") memeriksa. Yang TIDAK dipegang QA adalah `lock`
            // (finalisasi) — itu wewenang reviewer/atasan.
            // AGENT: asisten | 2026-10-03 | tambah permission QA sampling | modul KPI, fase 2 QA sampling
            'qa_sampling.view',
            'qa_sampling.create',
            'qa_sampling.update',
            'qa_sampling.delete',
            'qa_sampling.submit',
            'qa_sampling.review',
            'qa_sampling.cancel',
            // Laporan: QA memakainya untuk memperbaiki kualitas penilaiannya
            // sendiri (parameter mana yang paling sering NO). Target hanya
            // DIBACA — QA bukan pihak yang menetapkan target, agar capaian
            // sampling tidak dapat "diatur" oleh pihak yang dinilai.
            // AGENT: asisten | 2026-10-04 | tambah permission QA laporan & target | modul KPI, fase 3 QA reporting
            'qa_sampling.report.view',
            'qa_sampling_target.view',
            // Quiz: QA adalah pemilik isi quiz — menyusun bank soal, template,
            // versi, dan menerbitkannya. QA juga melihat hasil untuk pembinaan.
            // AGENT: asisten | 2026-10-05 | tambah permission QA quiz | modul Quiz, fase 4 quiz
            'question_bank.view',
            'question_bank.create',
            'question_bank.update',
            'question_bank.delete',
            'quiz.view',
            'quiz.create',
            'quiz.update',
            'quiz.delete',
            'quiz.publish',
            'quiz_result.view',
            // QA Assessment: QA adalah PEMIKIR & PELAKSANA penilaian kualitatif &
            // ujian tertulis (PRD QA Assessment). CRUD penuh untuk sub-bagian A,
            // B, C. Admin hanya mengatur wadah (periode, penugasan agent).
            // AGENT: asisten | 2026-10-08 | tambah permission QA QA Assessment | modul QA Assessment
            'qa.assessment.category.view',
            'qa.assessment.category.create',
            'qa.assessment.category.update',
            'qa.assessment.category.delete',
            'qa.assessment.view',
            'qa.assessment.create',
            'qa.assessment.update',
            'qa.assessment.delete',
            'qa.assessment.publish',
            'qa.exam.question-bank.view',
            'qa.exam.question-bank.create',
            'qa.exam.question-bank.update',
            'qa.exam.question-bank.delete',
            'qa.exam.form.view',
            'qa.exam.form.create',
            'qa.exam.form.update',
            'qa.exam.form.delete',
            'qa.exam.form.publish',
            'qa.exam.assignment.view',
            'qa.exam.assignment.create',
            'qa.exam.assignment.update',
            'qa.exam.assignment.delete',
            'qa.exam.assignment.grade',
            'qa.exam.assignment.publish',
            'qa.exam.answer.view',
            'qa.exam.answer.create',
            'qa.exam.answer.update',
            'qa.exam.answer.delete',
            'qa.period.view',
            'qa.agent-assignment.view',
        ]);

        // Employee: hanya melihat informasi
        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $employee->syncPermissions([
            'information.view',
            'category.view',
            // Quiz: karyawan boleh MENGERJAKAN quiz (PRD §27). Ia hanya melihat
            // attempt miliknya lewat QuizAttemptPolicy — bukan izin ini.
            // AGENT: asisten | 2026-10-05 | tambah permission employee quiz | modul Quiz, fase 4 quiz
            'quiz.take',
            // QA Assessment: agent melihat assessment miliknya & mengerjakan ujian.
            // Policy mengeforsikan kepemilikan (view own, take own).
            // AGENT: asisten | 2026-10-08 | tambah permission agent QA Assessment | modul QA Assessment
            'qa.assessment.view',
            'qa.exam.assignment.view',
            'qa.exam.answer.create',
            'qa.exam.answer.update',
        ]);

        // ------------------------------------------------------------------
        // Role fungsional tambahan (PRD §11.1) — 7 role total.
        //
        // Catatan: modul yang menjadi konsumen utama role-role ini
        // (KPI, QA Assessment, Ticketing) belum dibangun. Permission di sini
        // sengaja dibatasi pada yang sudah ada agar tidak ada izin "hantu"
        // yang tidak dipakai kode mana pun. Izin khusus modul tersebut
        // menyusul saat modulnya dikerjakan.
        // ------------------------------------------------------------------

        // Supervisor: melihat laporan tim sendiri (bukan menilai).
        $supervisor = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $supervisor->syncPermissions([
            'information.view',
            'category.view',
            'team.view',
            // QA Sampling: supervisor MEMERIKSA dan MENGUNCI hasil timnya
            // (PRD §35 "Hasil QA ... Detail assessment").
            // AGENT: asisten | 2026-10-03 | tambah permission supervisor QA sampling | modul KPI, fase 2 QA sampling
            'qa_sampling.view',
            'qa_sampling.review',
            'qa_sampling.lock',
            // Laporan + target (read-only): supervisor butuh melihat capaian
            // dan parameter yang paling sering salah untuk membina timnya.
            // Mengubah target bukan wewenangnya.
            // AGENT: asisten | 2026-10-04 | tambah permission supervisor laporan & target | modul KPI, fase 3 QA reporting
            'qa_sampling.report.view',
            'qa_sampling_target.view',
            // Quiz: supervisor melihat bank soal (read) & hasil quiz timnya untuk
            // pembinaan, tetapi tidak menyusun/menerbitkan quiz (itu QA).
            // AGENT: asisten | 2026-10-05 | tambah permission supervisor quiz | modul Quiz, fase 4 quiz
            'question_bank.view',
            'quiz.view',
            'quiz_result.view',
            // QA Assessment: supervisor melihat hasil timnya (read-only) untuk pembinaan.
            // AGENT: asisten | 2026-10-08 | tambah permission supervisor QA Assessment | modul QA Assessment
            'qa.assessment.view',
            'qa.exam.form.view',
            'qa.exam.assignment.view',
            'qa.exam.answer.view',
        ]);

        // Project Leader: sama seperti supervisor untuk tim yang dipimpin.
        $projectLeader = Role::firstOrCreate(['name' => 'project_leader', 'guard_name' => 'web']);
        $projectLeader->syncPermissions([
            'information.view',
            'category.view',
            'team.view',
        ]);

        // IT: tiketing, log sistem, bantu akun. Tanpa user.disable (tidak boleh
        // menghapus/menonaktifkan akun) sesuai batasan di PRD §11.1.
        $it = Role::firstOrCreate(['name' => 'it', 'guard_name' => 'web']);
        $it->syncPermissions([
            'information.view',
            'category.view',
            'user.view',
            // Tiket: IT Support adalah pemilik antrean tiket (PRD §42).
            'ticket.view',
            'ticket.update',
            'ticket.assign',
        ]);

        // HR: melihat nilai KPI semua karyawan (read-only). Divisi memang
        // dikelola Admin dan HR (PRD §11), jadi HR boleh MELIHAT struktur
        // organisasi — tetapi tidak mengubahnya (tanpa department.update).
        $hr = Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'web']);
        $hr->syncPermissions([
            'information.view',
            'category.view',
            'user.view',
            'department.view',
            'team.view',
            // QA Sampling: HR read-only atas SELURUH hasil (PRD §35 "HR:
            // Read-only"), tanpa hak mengubah apa pun.
            // AGENT: asisten | 2026-10-03 | tambah permission HR QA sampling | modul KPI, fase 2 QA sampling
            'qa_sampling.view',
            // HR memakai laporan untuk kebutuhan penilaian kinerja lintas
            // divisi (PRD §35 "Hasil QA ... semua karyawan"). Target dibaca
            // saja: yang menetapkan kebijakan sampling adalah Admin.
            // AGENT: asisten | 2026-10-04 | tambah permission HR laporan & target | modul KPI, fase 3 QA reporting
            'qa_sampling.report.view',
            'qa_sampling_target.view',
            // Quiz: HR read-only atas bank soal & hasil quiz semua karyawan
            // (PRD §35 "HR: Read-only"), tanpa hak menyusun/menerbitkan.
            // AGENT: asisten | 2026-10-05 | tambah permission HR quiz | modul Quiz, fase 4 quiz
            'question_bank.view',
            'quiz.view',
            'quiz_result.view',
            // QA Assessment: HR read-only atas SELURUH hasil (PRD §35 "HR:
            // Read-only"), untuk kebutuhan penilaian kinerja lintas divisi.
            // AGENT: asisten | 2026-10-08 | tambah permission HR QA Assessment | modul QA Assessment
            'qa.assessment.view',
            'qa.exam.form.view',
            'qa.exam.assignment.view',
            'qa.exam.answer.view',
        ]);
    }
}
