<?php

namespace App\Providers;

use App\Models\AppNotification;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\InformationCategory;
use App\Models\InformationItem;
use App\Models\Kpi;
use App\Models\Message;
use App\Models\QaSamplingAssessment;
use App\Models\QaSamplingTarget;
use App\Models\ExamAnswer;
use App\Models\ExamAssignment;
use App\Models\ExamForm;
use App\Models\QuestionBank;
use App\Models\QuestionCategory;
use App\Models\QaAgentAssignment;
use App\Models\QaAssessment;
use App\Models\QaAssessmentCategory;
use App\Models\QaExamQuestionBank;
use App\Models\QaPeriod;
use App\Models\QuizAttempt;
use App\Models\QuizTemplate;
use App\Models\QuizVersion;
use App\Models\Scorecard;
use App\Models\ScorecardCategory;
use App\Models\ScorecardParameter;
use App\Models\ScorecardVersion;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkNote;
use App\Policies\ChannelPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\ExamAnswerPolicy;
use App\Policies\ExamAssignmentPolicy;
use App\Policies\ExamFormPolicy;
use App\Policies\InformationPolicy;
use App\Policies\KpiPolicy;
use App\Policies\MessagePolicy;
use App\Policies\QaSamplingAssessmentPolicy;
use App\Policies\QaSamplingTargetPolicy;
use App\Policies\QuestionBankPolicy;
use App\Policies\QuestionCategoryPolicy;
use App\Policies\QaAgentAssignmentPolicy;
use App\Policies\QaAssessmentCategoryPolicy;
use App\Policies\QaAssessmentPolicy;
use App\Policies\QaExamQuestionBankPolicy;
use App\Policies\QaPeriodPolicy;
use App\Policies\QuizAttemptPolicy;
use App\Policies\QuizTemplatePolicy;
use App\Policies\QuizVersionPolicy;
use App\Policies\RolePolicy;
use App\Policies\ScorecardCategoryPolicy;
use App\Policies\ScorecardParameterPolicy;
use App\Policies\ScorecardPolicy;
use App\Policies\ScorecardVersionPolicy;
use App\Policies\TeamPolicy;
use App\Policies\TicketPolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkNotePolicy;
use App\Services\ChatService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // JANGAN daftarkan `Relation::morphMap()` di sini.
        //
        // Peta morph bersifat GLOBAL: begitu `user` dipetakan ke
        // `App\Models\User`, seluruh relasi polimorfik di aplikasi ini ikut
        // memakai alias — termasuk `model_has_roles` / `model_has_permissions`
        // milik spatie. Baris yang sudah ada menyimpan FQCN, sehingga
        // `$user->hasRole('admin')` gagal, semua `@can` bernilai false, dan
        // menu admin di sidebar hilang (regresi 2026-10-10).
        //
        // Alias `subject_type` audit diresolusi LOKAL di `AuditLog`
        // (`subjectAlias()` / `resolveSubjectClass()`) supaya efeknya tidak
        // meluas. Dijaga test:
        // `AuditLogExecutionTest::test_audit_does_not_register_a_global_morph_map`.
        // AGENT: asisten | 2026-10-10 | batalkan morph map global | modul Audit Log, §11.15

        // Policies
        Gate::policy(InformationItem::class, InformationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);

        // Policy: aksi per-PESAN pada Chat (PRD Chat §15) — sunting, hapus, semat.
        // AGENT: asisten | 2026-10-06 | daftarkan MessagePolicy | modul Chat, fitur lanjutan
        // Dipisah dari ConversationPolicy: yang menentukan di sini adalah
        // kepemilikan pesan, bukan keanggotaan percakapan.
        Gate::policy(Message::class, MessagePolicy::class);

        // Policy: catatan kerja pribadi. Pemilik saja - admin pun tidak
        // (PRD Catatan). Lihat WorkNotePolicy untuk alasannya.
        // AGENT: asisten | 2026-10-01 | daftarkan WorkNotePolicy | modul Catatan Kerja, fase 1 backend
        Gate::policy(WorkNote::class, WorkNotePolicy::class);

        // Policy: master KPI & QA Scorecard (PRD Employee KPI/QA §35).
        // AGENT: asisten | 2026-10-02 | daftarkan policy master KPI | modul KPI, fase 1 master
        // Admin memegang master aturan penilaian; QA hanya read-only.
        Gate::policy(Kpi::class, KpiPolicy::class);
        Gate::policy(Channel::class, ChannelPolicy::class);
        Gate::policy(Scorecard::class, ScorecardPolicy::class);
        Gate::policy(ScorecardVersion::class, ScorecardVersionPolicy::class);
        Gate::policy(ScorecardCategory::class, ScorecardCategoryPolicy::class);
        Gate::policy(ScorecardParameter::class, ScorecardParameterPolicy::class);

        // Policy: QA Sampling (PRD Employee KPI/QA §35/§45) — fase 2.
        // AGENT: asisten | 2026-10-03 | daftarkan policy QA sampling | modul KPI, fase 2 QA sampling
        // QA mengisi penilaian; reviewer/supervisor memeriksa; karyawan hanya
        // melihat hasil miliknya. Aturan status (draft-only) ada di policy.
        Gate::policy(QaSamplingAssessment::class, QaSamplingAssessmentPolicy::class);

        // Policy: Target Sampling (PRD Employee KPI/QA §17/§18) — fase 3.
        // AGENT: asisten | 2026-10-04 | daftarkan policy Target Sampling | modul KPI, fase 3 QA reporting
        // Target adalah KONFIGURASI KEBIJAKAN: Admin mengelola, QA/Supervisor/
        // HR membacanya. Aturan "aturan yang sudah berlaku tidak dihapus" ada
        // di policy, bukan di controller.
        Gate::policy(QaSamplingTarget::class, QaSamplingTargetPolicy::class);

        // Policy: QA Assessment (PRD QA Assessment §10).
        // AGENT: asisten | 2026-10-08 | daftarkan policies QA Assessment | modul QA Assessment
        Gate::policy(QaAssessmentCategory::class, QaAssessmentCategoryPolicy::class);
        Gate::policy(QaAssessment::class, QaAssessmentPolicy::class);
        Gate::policy(QaExamQuestionBank::class, QaExamQuestionBankPolicy::class);
        Gate::policy(ExamForm::class, ExamFormPolicy::class);
        Gate::policy(ExamAssignment::class, ExamAssignmentPolicy::class);
        Gate::policy(ExamAnswer::class, ExamAnswerPolicy::class);
        Gate::policy(QaPeriod::class, QaPeriodPolicy::class);
        Gate::policy(QaAgentAssignment::class, QaAgentAssignmentPolicy::class);

        // Policy: Quiz / Knowledge Assessment (PRD Employee KPI/QA §20-§27) — fase 4.
        // AGENT: asisten | 2026-10-05 | daftarkan policy Quiz | modul Quiz, fase 4 quiz
        // Bank soal & template disusun QA; attempt milik karyawan yang mengerjakan.
        // Aturan "versi yang sudah dikerjakan terkunci" ada di QuizVersionPolicy.
        Gate::policy(QuestionBank::class, QuestionBankPolicy::class);
        Gate::policy(QuestionCategory::class, QuestionCategoryPolicy::class);
        Gate::policy(QuizTemplate::class, QuizTemplatePolicy::class);
        Gate::policy(QuizVersion::class, QuizVersionPolicy::class);
        Gate::policy(QuizAttempt::class, QuizAttemptPolicy::class);

        // Gate: akses menu administrasi (hanya Admin)
        Gate::define('admin-access', function (User $user) {
            return $user->hasRole('admin');
        });

        // Gate: struktur organisasi (divisi & tim).
        // Berbeda dari `admin-access` yang khusus Admin: HR perlu melihat
        // divisi, dan supervisor/project leader perlu melihat tim. Policy tetap
        // membatasi siapa yang boleh MENGUBAH (department.*/team.* penuh).
        Gate::define('manage-organization', function (User $user) {
            return $user->canAny(['department.view', 'team.view']);
        });

        // Gate: halaman muka Supervisor (/tim) — PRD Employee KPI/QA §35
        // "supervisor melihat nilai + laporan tim".
        // AGENT: asisten | 2026-10-20 | tambah gate dashboard supervisor & HR | modul Dashboard per peran
        //
        // Sengaja khusus peran, BUKAN `manage-organization`: gate itu juga
        // meloloskan HR (yang punya `team.view`), dan HR harus memakai
        // halamannya sendiri yang tidak menampilkan nama karyawan.
        //
        // `project_leader` belum ikut: DashboardScope juga belum memasukkannya
        // sebagai pemegang data tim, sehingga ia hanya akan melihat dirinya
        // sendiri di halaman ini — halaman kosong yang membingungkan.
        Gate::define('supervisor-access', function (User $user) {
            return $user->hasRole('supervisor');
        });

        // Gate: halaman muka HR (/hr) — PRD Employee KPI/QA §35 "HR: Read-only".
        Gate::define('hr-access', function (User $user) {
            return $user->hasRole('hr');
        });

        // Gate: pengelolaan konten informasi — QA (Content Management).
        // PRD §1/§16 — Admin adalah Application Management, bukan content creator,
        // sehingga Admin tidak lagi otomatis lolos ke area /qa/*.
        Gate::define('manage-information', function (User $user) {
            return $user->hasRole('qa')
                || $user->can('information.create');
        });

        // Gate: area IT Support (/it/*) — antrean tiket & dashboard IT.
        // PRD §42 (Phase 5 — IT) & §11.1: role `it` adalah pemilik modul ini;
        // Admin ikut serta karena mengelola wadah aplikasi.
        Gate::define('manage-tickets', function (User $user) {
            return $user->can('ticket.view');
        });

        // Gate: area QA Sampling (/qa/sampling/*) — PRD Employee KPI/QA §35.
        // AGENT: asisten | 2026-10-03 | tambah gate QA sampling | modul KPI, fase 2 QA sampling
        //
        // Gate ini hanya menjawab "boleh membuka area", bukan "boleh apa".
        // Yang boleh masuk: pemegang izin `qa_sampling.view` (QA, Admin,
        // Supervisor, HR) DAN karyawan biasa.
        //
        // Mengapa karyawan ikut diizinkan masuk: PRD §35 memberi karyawan hak
        // "Hasil QA sendiri" dan "Histori penilaian". Bila gate ini menolak
        // mereka, hak itu tidak akan pernah bisa diakses. Yang membatasi
        // mereka bukan gate, melainkan QaSamplingAssessmentPolicy - karyawan
        // hanya lolos untuk baris MILIKNYA dan hanya setelah tidak lagi
        // draft, sedangkan aksi create/update/submit tetap ditolak.
        Gate::define('access-qa-sampling', function (User $user) {
            return $user->can('qa_sampling.view') || $user->hasRole('employee');
        });

        // Gate: area Quiz (/quiz/*) — PRD Employee KPI/QA §20-§27.
        // AGENT: asisten | 2026-10-05 | tambah gate quiz | modul Quiz, fase 4 quiz
        //
        // Seperti gate QA sampling, gate ini hanya menjawab "boleh membuka area".
        // Yang boleh masuk: pemegang izin `quiz.view` (QA/Admin/Supervisor/HR)
        // DAN karyawan biasa (untuk mengerjakan quiz & melihat hasilnya). Yang
        // membatasi karyawan adalah QuizAttemptPolicy: ia hanya lolos untuk
        // attempt MILIKNYA.
        Gate::define('access-quiz', function (User $user) {
            return $user->can('quiz.view') || $user->hasRole('employee');
        });

        // Gate: area QA Assessment (/qa/assessment/*) — PRD QA Assessment §10.
        // AGENT: asisten | 2026-10-08 | tambah gate QA Assessment | modul QA Assessment
        //
        // Yang boleh masuk: pemegang izin `qa.assessment.view` (QA, Admin,
        // Supervisor, HR) DAN karyawan biasa (untuk melihat assessment &
        // mengerjakan ujian miliknya). Policy membatasi akses ke data sendiri.
        Gate::define('access-qa-assessment', function (User $user) {
            return $user->can('qa.assessment.view') || $user->hasRole('employee');
        });

        // View composer: jumlah notifikasi belum dibaca untuk badge di topbar.
        View::composer('layouts.partials.topbar', function ($view) {
            $user = auth()->user();

            $view->with(
                'unreadNotificationCount',
                $user ? AppNotification::where('user_id', $user->id)->unread()->count() : 0,
            );
        });

        // View composer: jumlah pesan chat belum dibaca untuk badge di sidebar.
        // Dihitung hanya saat sidebar benar-benar dirender, bukan setiap
        // request.
        //
        // Dijaga `Schema::hasTable()` karena composer ini berjalan di SETIAP
        // halaman: bila kode sudah diperbarui tetapi migration chat belum
        // dijalankan (mis. deploy bertahap), seluruh aplikasi akan gagal
        // dengan error 500. Dengan penjagaan ini, badge sekadar tidak muncul
        // sampai migration dijalankan.
        View::composer('layouts.partials.sidebar', function ($view) {
            $user = auth()->user();

            $count = 0;

            if ($user && Schema::hasTable('conversation_members')) {
                $count = app(ChatService::class)->totalUnread($user);
            }

            $view->with('unreadChatCount', $count);
        });

        // View composer: kategori aktif untuk sidebar (PRD Redesign STEP 4).
        // Hanya diambil saat sidebar dirender, bukan setiap request.
        View::composer('layouts.partials.sidebar', function ($view) {
            $view->with(
                'sidebarCategories',
                InformationCategory::active()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(),
            );
        });
    }
}
