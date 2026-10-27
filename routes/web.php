<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Admin\EmployeeImportController;
use App\Http\Controllers\Admin\FeatureSettingController as AdminFeatureSettingController;
use App\Http\Controllers\Admin\Kpi\ChannelController as AdminKpiChannelController;
use App\Http\Controllers\Admin\Kpi\KpiController as AdminKpiController;
use App\Http\Controllers\Admin\Kpi\QaSamplingTargetController as AdminQaSamplingTargetController;
use App\Http\Controllers\Admin\Kpi\ScorecardCategoryController as AdminScorecardCategoryController;
use App\Http\Controllers\Admin\Kpi\ScorecardController as AdminScorecardController;
use App\Http\Controllers\Admin\Kpi\ScorecardParameterController as AdminScorecardParameterController;
use App\Http\Controllers\Admin\Kpi\ScorecardVersionController as AdminScorecardVersionController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Chat\ConversationController as ChatConversationController;
use App\Http\Controllers\Chat\DirectoryController as ChatDirectoryController;
use App\Http\Controllers\Chat\MessageController as ChatMessageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Hr\DashboardController as HrDashboardController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\It\TicketController as ItTicketController;
use App\Http\Controllers\Notes\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Qa\DashboardController as QaDashboardController;
use App\Http\Controllers\Qa\ExamFormController;
use App\Http\Controllers\Qa\InformationController as QaInformationController;
use App\Http\Controllers\Qa\QaAgentAssignmentController;
use App\Http\Controllers\Qa\QaAssessmentCategoryController;
use App\Http\Controllers\Qa\QaAssessmentController;
use App\Http\Controllers\Qa\QaExamAttemptController;
use App\Http\Controllers\Qa\QaExamQuestionBankController;
use App\Http\Controllers\Qa\QaPeriodController;
use App\Http\Controllers\Qa\Quiz\QuestionBankController as QaQuizQuestionBankController;
use App\Http\Controllers\Qa\Quiz\QuestionCategoryController as QaQuizQuestionCategoryController;
use App\Http\Controllers\Qa\Quiz\QuizAttemptController as QaQuizAttemptController;
use App\Http\Controllers\Qa\Quiz\QuizTemplateController as QaQuizTemplateController;
use App\Http\Controllers\Qa\Quiz\QuizVersionController as QaQuizVersionController;
use App\Http\Controllers\Qa\Sampling\AssessmentController as QaSamplingAssessmentController;
use App\Http\Controllers\Qa\Sampling\ReportController as QaSamplingReportController;
use App\Http\Controllers\Qa\TagController as QaTagController;
use App\Http\Controllers\RootRedirectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Supervisor\DashboardController as SupervisorDashboardController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public / Guest
|--------------------------------------------------------------------------
*/
// Perilaku tidak berubah: "/" cuma mengarahkan ke beranda atau halaman masuk.
// Dipindah dari closure ke controller supaya `php artisan route:cache`
// (bagian dari `php artisan optimize`) bisa dijalankan di server produksi.
// AGENT: asisten | 2026-10-27 | closure -> controller | persiapan rilis produksi
Route::get('/', RootRedirectController::class)->name('root');

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Beranda
    Route::get('/beranda', [HomeController::class, 'index'])->name('home');

    // Pencarian
    Route::get('/cari', [SearchController::class, 'index'])->name('search');

    // Pencarian langsung (JSON) untuk kotak pencarian dengan debounce.
    // Dibatasi 30 permintaan/menit/pengguna supaya mengetik cepat tidak
    // berubah menjadi puluhan query lintas modul.
    // AGENT: asisten | 2026-10-16 | tambah rute pencarian lintas modul | modul Pencarian
    Route::get('/cari/langsung', [SearchController::class, 'live'])
        ->middleware('throttle:30,1')
        ->name('search.live');

    // Pusat Informasi (Employee)
    Route::prefix('informasi')->name('information.')->group(function () {
        Route::get('/', [InformationController::class, 'index'])->name('index');
        Route::get('/{slug}/dokumen', [InformationController::class, 'document'])
            ->middleware(['throttle:30,1'])
            ->name('document');
        Route::get('/{slug}/download', [InformationController::class, 'download'])
            ->middleware(['throttle:20,1'])
            ->name('download');
        Route::get('/{slug}', [InformationController::class, 'show'])->name('show');
        // Kategori (diletakkan setelah show agar tidak bentrok, tapi pakai prefix khusus)
        Route::get('/kategori/{slug}', [InformationController::class, 'category'])->name('category');
    });

    // Profil
    Route::prefix('profil')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });

    // Notifikasi
    Route::prefix('notifikasi')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/baca-semua', [NotificationController::class, 'readAll'])->name('readAll');
        Route::post('/{notification}/baca', [NotificationController::class, 'read'])->name('read');
    });

    /*
    |----------------------------------------------------------------------
    | Chat internal (PRD Chat) — terbuka untuk semua karyawan
    |----------------------------------------------------------------------
    |
    | Otorisasi TIDAK dipegang middleware, melainkan ConversationPolicy:
    | percakapan bersifat privat per anggota, jadi middleware berbasis
    | permission (mis. `can:chat.view`) justru akan memberi akses yang salah.
    | "Anggota atau bukan" hanya dapat diputuskan setelah percakapan diketahui,
    | yaitu di dalam controller lewat `authorize()`.
    |
    | Urutan route penting: `/chat/karyawan` harus mendahului `/chat/{conversation}`
    | agar tidak tertangkap sebagai id percakapan. Hal yang sama berlaku untuk
    | `/chat/belum-dibaca` dan `/chat/daftar-percakapan`.
    */
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/', [ChatConversationController::class, 'index'])->name('index');

        /*
        | Polling PRD §9 (target badge & daftar percakapan). Keduanya dipanggil
        | rutin dari halaman, jadi throttlenya dilonggarkan seperti polling
        | pesan — tetapi tetap dibatasi supaya tidak dapat dijadikan alat
        | pembanjir permintaan.
        */
        Route::get('/belum-dibaca', [ChatConversationController::class, 'unread'])
            ->middleware(['throttle:240,1'])
            ->name('unread');

        Route::get('/daftar-percakapan', [ChatConversationController::class, 'poll'])
            ->middleware(['throttle:240,1'])
            ->name('conversations.poll');

        // Kanal (hanya admin/qa — dijaga ConversationPolicy::create)
        Route::post('/kanal', [ChatConversationController::class, 'store'])->name('channels.store');
        Route::put('/kanal/{conversation}', [ChatConversationController::class, 'update'])->name('channels.update');
        Route::post('/kanal/{conversation}/arsip', [ChatConversationController::class, 'archive'])->name('channels.archive');
        Route::post('/kanal/{conversation}/anggota', [ChatConversationController::class, 'addMembers'])->name('members.store');
        Route::delete('/kanal/{conversation}/anggota/{user}', [ChatConversationController::class, 'removeMember'])->name('members.destroy');

        // Daftar karyawan untuk memulai DM (PRD §5 fitur #3)
        Route::get('/karyawan', [ChatDirectoryController::class, 'index'])->name('directory');
        Route::post('/karyawan/{user}/mulai', [ChatDirectoryController::class, 'store'])->name('direct.start');

        // Lampiran (diperiksa keanggotaannya, bukan URL publik)
        Route::get('/lampiran/{attachment}', [ChatMessageController::class, 'attachment'])
            ->middleware(['throttle:60,1'])
            ->name('attachments.show');

        Route::get('/{conversation}', [ChatMessageController::class, 'show'])->name('show');

        // Kirim pesan dibatasi 60 pesan/menit/pengguna (PRD §8).
        Route::post('/{conversation}/pesan', [ChatMessageController::class, 'store'])
            ->middleware(['throttle:60,1'])
            ->name('messages.store');

        // Polling pesan baru (PRD §9) — longgar throttlenya karena rutin.
        Route::get('/{conversation}/pesan/baru', [ChatMessageController::class, 'poll'])
            ->middleware(['throttle:240,1'])
            ->name('messages.poll');

        // B3: muat pesan lama sebelum sebuah id pesan (saat pengguna
        // menggulir ke atas). Path segmen berbeda dari `pesan/baru`, tidak
        // bentrok. Frekuensinya rendah, tapi tetap dibatasi.
        Route::get('/{conversation}/pesan/sebelum/{message}', [ChatMessageController::class, 'before'])
            ->middleware(['throttle:60,1'])
            ->name('messages.before');

        /*
        | Fitur lanjutan PRD §15: sunting, hapus, semat, cari, utas.
        |
        | Otorisasi per-pesan dipegang MessagePolicy (kepemilikan pesan),
        | dipanggil di controller. `{message}` sengaja TIDAK di-scope ke
        | `{conversation}` di tingkat route: pengikatan model Laravel akan
        | mengembalikan 404 yang sama, tetapi controller tetap memeriksa
        | kepemilikan percakapan secara eksplisit supaya pesan percakapan
        | lain tidak dapat disentuh lewat id percakapan yang salah.
        |
        | AGENT: asisten | 2026-10-06 | tambah route pesan lanjutan | modul Chat §15
        */
        Route::get('/{conversation}/pesan/cari', [ChatMessageController::class, 'search'])
            ->middleware(['throttle:120,1'])
            ->name('messages.search');

        Route::get('/{conversation}/pesan/{message}/utas', [ChatMessageController::class, 'thread'])
            ->middleware(['throttle:120,1'])
            ->name('messages.thread');

        // Menyunting & menghapus dibatasi seperti mengirim: 60 aksi/menit.
        Route::put('/{conversation}/pesan/{message}', [ChatMessageController::class, 'update'])
            ->middleware(['throttle:60,1'])
            ->name('messages.update');

        Route::delete('/{conversation}/pesan/{message}', [ChatMessageController::class, 'destroy'])
            ->middleware(['throttle:60,1'])
            ->name('messages.destroy');

        Route::post('/{conversation}/pesan/{message}/sematkan', [ChatMessageController::class, 'pin'])
            ->middleware(['throttle:60,1'])
            ->name('messages.pin');
    });

    /*
    |----------------------------------------------------------------------
    | Catatan kerja pribadi (PRD Catatan) — terbuka untuk semua karyawan
    |----------------------------------------------------------------------
    |
    | Otorisasi TIDAK dipegang middleware, melainkan WorkNotePolicy: yang
    | menentukan akses adalah kepemilikan catatan, bukan permission. Karena
    | itu setiap aksi memanggil `authorize()` di controller.
    |
    | Urutan route penting: `/catatan/{note}/pulihkan` dan `/sematkan` harus
    | mendahului `/catatan/{note}` yang menulis, dan `restore` memakai
    | `withTrashed()` karena catatan yang dihapus tetap harus dapat ditemukan.
    |
    | Pembatasan frekuensi tulis penting di sini karena autosave: tanpa
    | `throttle`, mengetik cepat dapat membanjiri server.
    |
    | AGENT: asisten | 2026-10-01 | tambah grup route notes.* | modul Catatan Kerja, fase 1 backend
    */
    Route::prefix('catatan')->name('notes.')->group(function () {
        Route::get('/', [NoteController::class, 'index'])->name('index');
        Route::post('/', [NoteController::class, 'store'])
            ->middleware(['throttle:'.config('notes.write_rate_limit').',1'])
            ->name('store');

        Route::put('/{note}', [NoteController::class, 'update'])
            ->middleware(['throttle:'.config('notes.write_rate_limit').',1'])
            ->name('update');

        Route::post('/{note}/sematkan', [NoteController::class, 'pin'])
            ->middleware(['throttle:60,1'])
            ->name('pin');

        Route::delete('/{note}', [NoteController::class, 'destroy'])
            ->middleware(['throttle:60,1'])
            ->name('destroy');

        Route::post('/{note}/pulihkan', [NoteController::class, 'restore'])
            ->withTrashed()
            ->middleware(['throttle:60,1'])
            ->name('restore');

        /*
        | Ekspor (PRD Catatan §14).
        |
        | `ekspor/semua` harus MENDAHULUI `ekspor/{note}` agar tidak tertangkap
        | sebagai id catatan. Ekspor memakai GET karena hanya membaca dan
        | mengunduh; otorisasinya tetap dijaga (pemilik saja).
        |
        | AGENT: asisten | 2026-10-06 | tambah route ekspor catatan | modul Catatan, lanjutan
        */
        Route::get('/ekspor/semua', [NoteController::class, 'exportAll'])
            ->middleware(['throttle:30,1'])
            ->name('export.all');

        Route::get('/ekspor/{note}', [NoteController::class, 'export'])
            ->middleware(['throttle:30,1'])
            ->name('export');

        /*
        | Label catatan (PRD Catatan §14).
        |
        | `label/{tag}` (DELETE) hanya boleh oleh pemilik label — diperiksa di
        | controller karena label tidak punya policy sendiri (aturannya sama
        | dengan seluruh modul: pemilik saja).
        |
        | AGENT: asisten | 2026-10-06 | tambah route label catatan | modul Catatan, lanjutan
        */
        Route::post('/label', [NoteController::class, 'storeTag'])
            ->middleware(['throttle:60,1'])
            ->name('tags.store');

        Route::delete('/label/{tag}', [NoteController::class, 'destroyTag'])
            ->middleware(['throttle:60,1'])
            ->name('tags.destroy');

        Route::put('/{note}/label', [NoteController::class, 'syncTags'])
            ->middleware(['throttle:'.config('notes.write_rate_limit').',1'])
            ->name('tags.sync');
    });

    /*
    |----------------------------------------------------------------------
    | Admin — Application Management (kategori, user, dsb.)
    |----------------------------------------------------------------------
    */
    Route::prefix('admin')->name('admin.')->middleware(['can:admin-access'])->group(function () {
        // Halaman muka Admin. Didaftarkan paling awal dan TANPA segmen tambahan
        // supaya `/admin` tidak lagi 404: sidebar sebelumnya melempar Admin
        // langsung ke Master Informasi, sehingga tidak ada tempat untuk melihat
        // keadaan wadah aplikasi (akun tanpa peran, perubahan terakhir).
        // AGENT: asisten | 2026-10-16 | tambah rute dashboard admin | modul Dashboard per peran
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Master Informasi
        Route::get('/kategori', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::get('/kategori/tambah', [AdminCategoryController::class, 'create'])->name('categories.create');
        Route::post('/kategori', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::get('/kategori/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/kategori/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/kategori/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

        // Manajemen Pengguna
        Route::get('/pengguna', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/tambah', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/pengguna', [AdminUserController::class, 'store'])->name('users.store');

        // Impor massal (didaftarkan SEBELUM '/pengguna/{user}' agar kata
        // "impor" tidak tertangkap sebagai parameter {user}).
        Route::get('/pengguna/impor', [EmployeeImportController::class, 'create'])->name('users.import');
        Route::get('/pengguna/impor/template', [EmployeeImportController::class, 'template'])->name('users.import.template');
        Route::post('/pengguna/impor/pratinjau', [EmployeeImportController::class, 'preview'])->name('users.import.preview');
        Route::post('/pengguna/impor', [EmployeeImportController::class, 'store'])->name('users.import.store');

        Route::get('/pengguna/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::get('/pengguna/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/pengguna/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::post('/pengguna/{user}/status', [AdminUserController::class, 'toggleStatus'])->name('users.status');
        Route::delete('/pengguna/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        // Audit Log (read-only)
        Route::get('/audit', [AdminAuditLogController::class, 'index'])->name('audit.index');

        // Ekspor CSV didaftarkan SEBELUM '/audit/{audit}' agar kata "ekspor"
        // tidak tertangkap sebagai parameter {audit} (pola yang sama dipakai
        // '/pengguna/impor' di atas).
        Route::get('/audit/ekspor', [AdminAuditLogController::class, 'export'])->name('audit.export');

        // Ekspor PDF rekap audit: dipakai untuk lampiran laporan/rapat, di mana
        // CSV sulit dibaca. Filter yang aktif di layar ikut terbawa.
        // AGENT: asisten | 2026-10-16 | tambah ekspor PDF audit | modul Audit Log
        Route::get('/audit/pdf', [AdminAuditLogController::class, 'pdf'])->name('audit.pdf');

        Route::get('/audit/{audit}', [AdminAuditLogController::class, 'show'])->name('audit.show');

        // Pengaturan Fitur (saklar on/off). Khusus Admin: mematikan fitur
        // mengubah perilaku seluruh pengguna.
        // AGENT: asisten | 2026-10-16 | tambah halaman pengaturan fitur | modul Pengaturan Fitur
        Route::get('/pengaturan-fitur', [AdminFeatureSettingController::class, 'index'])->name('features.index');
        Route::put('/pengaturan-fitur', [AdminFeatureSettingController::class, 'update'])->name('features.update');

        // Role & Permission
        // NB: parameter diikat ke kolom `name` (bukan id), karena nama role
        // itulah yang stabil dan dipakai di seluruh aplikasi.
        Route::get('/peran', [AdminRoleController::class, 'index'])->name('roles.index');
        Route::get('/peran/tambah', [AdminRoleController::class, 'create'])->name('roles.create');
        Route::post('/peran', [AdminRoleController::class, 'store'])->name('roles.store');
        Route::get('/peran/{role:name}/edit', [AdminRoleController::class, 'edit'])->name('roles.edit');
        Route::put('/peran/{role:name}', [AdminRoleController::class, 'update'])->name('roles.update');
        Route::delete('/peran/{role:name}', [AdminRoleController::class, 'destroy'])->name('roles.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Struktur Organisasi — Divisi & Tim
    |----------------------------------------------------------------------
    |
    | Sengaja TIDAK memakai gate `admin-access` (yang khusus Admin), karena
    | HR perlu melihat divisi dan supervisor/project leader perlu melihat tim.
    | Yang membatasi perubahan adalah policy divisi & tim.
    |
    | NB: `Department` dan `Team` diikat lewat kolom `slug` (lihat
    | getRouteKeyName()), mengikuti pola `{role:name}` di atas — memakai
    | penanda yang stabil, bukan id.
    */
    Route::prefix('admin')->name('admin.')->middleware(['can:manage-organization'])->group(function () {
        // Divisi
        Route::get('/divisi', [AdminDepartmentController::class, 'index'])->name('departments.index');
        Route::get('/divisi/tambah', [AdminDepartmentController::class, 'create'])->name('departments.create');
        Route::post('/divisi', [AdminDepartmentController::class, 'store'])->name('departments.store');
        Route::get('/divisi/{department}/edit', [AdminDepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('/divisi/{department}', [AdminDepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/divisi/{department}', [AdminDepartmentController::class, 'destroy'])->name('departments.destroy');

        // Tim + keanggotaan
        Route::get('/tim', [AdminTeamController::class, 'index'])->name('teams.index');
        Route::get('/tim/tambah', [AdminTeamController::class, 'create'])->name('teams.create');
        Route::post('/tim', [AdminTeamController::class, 'store'])->name('teams.store');
        Route::get('/tim/{team}/edit', [AdminTeamController::class, 'edit'])->name('teams.edit');
        Route::put('/tim/{team}', [AdminTeamController::class, 'update'])->name('teams.update');
        Route::delete('/tim/{team}', [AdminTeamController::class, 'destroy'])->name('teams.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Dashboard per peran — Supervisor & HR
    |----------------------------------------------------------------------
    |
    | Sebelum ini hanya Admin dan QA yang punya halaman muka sendiri;
    | supervisor dan HR dilempar ke Beranda yang sama dengan karyawan.
    | Pekerjaan mereka berbeda: supervisor membina timnya, HR membaca agregat
    | lintas divisi untuk penilaian kinerja.
    |
    | Cakupan data TIDAK diatur di sini, melainkan di `DashboardScope` —
    | supervisor hanya tim yang ia pimpin, HR seluruh perusahaan TANPA nama
    | karyawan. Gate-nya khusus peran (bukan `manage-organization`), sebab
    | gate itu juga meloloskan HR ke halaman supervisor.
    |
    | AGENT: asisten | 2026-10-20 | tambah rute dashboard supervisor & HR | modul Dashboard per peran
    */
    Route::prefix('tim')->name('supervisor.')->middleware(['can:supervisor-access'])->group(function () {
        Route::get('/', [SupervisorDashboardController::class, 'index'])->name('dashboard');
    });

    Route::prefix('hr')->name('hr.')->middleware(['can:hr-access'])->group(function () {
        Route::get('/', [HrDashboardController::class, 'index'])->name('dashboard');
    });

    /*
    |----------------------------------------------------------------------
    | KPI & QA Scorecard - Master (PRD Employee KPI/QA §5-§13)
    |----------------------------------------------------------------------
    |
    | Gate `can:scorecard.view` dipakai sebagai pintu masuk karena Admin
    | (pengelola master) dan QA (read-only) sama-sama berhak MELIHAT master.
    | Yang membedakan hak mengubah adalah policy masing-masing model: hanya
    | `scorecard.update`/`kpi.*` milik Admin yang lolos, sehingga QA yang
    | menembak store/update tetap ditolak (403).
    |
    | Urutan penting: rute statis (`/versi/{version}`, `/scorecard/{scorecard}/versi`)
    | diletakkan sebelum rute yang memakai {scorecard} telanjang agar tidak
    | tertangkap sebagai slug scorecard.
    |
    | AGENT: asisten | 2026-10-02 | tambah grup route admin.kpi.* | modul KPI, fase 1 master
    */
    Route::prefix('admin/kpi')->name('admin.kpi.')->middleware(['can:scorecard.view'])->group(function () {
        // Channel KPI
        Route::get('/channel', [AdminKpiChannelController::class, 'index'])->name('channels.index');
        Route::get('/channel/tambah', [AdminKpiChannelController::class, 'create'])->name('channels.create');
        Route::post('/channel', [AdminKpiChannelController::class, 'store'])->name('channels.store');
        Route::get('/channel/{channel}/edit', [AdminKpiChannelController::class, 'edit'])->name('channels.edit');
        Route::put('/channel/{channel}', [AdminKpiChannelController::class, 'update'])->name('channels.update');
        Route::delete('/channel/{channel}', [AdminKpiChannelController::class, 'destroy'])->name('channels.destroy');

        // KPI
        Route::get('/kpi', [AdminKpiController::class, 'index'])->name('kpis.index');
        Route::get('/kpi/tambah', [AdminKpiController::class, 'create'])->name('kpis.create');
        Route::post('/kpi', [AdminKpiController::class, 'store'])->name('kpis.store');
        Route::get('/kpi/{kpi}/edit', [AdminKpiController::class, 'edit'])->name('kpis.edit');
        Route::put('/kpi/{kpi}', [AdminKpiController::class, 'update'])->name('kpis.update');
        Route::delete('/kpi/{kpi}', [AdminKpiController::class, 'destroy'])->name('kpis.destroy');

        // Scorecard
        Route::get('/scorecard', [AdminScorecardController::class, 'index'])->name('scorecards.index');
        Route::get('/scorecard/tambah', [AdminScorecardController::class, 'create'])->name('scorecards.create');
        Route::post('/scorecard', [AdminScorecardController::class, 'store'])->name('scorecards.store');

        // Versi scorecard (workspace)
        Route::post('/scorecard/{scorecard}/versi', [AdminScorecardController::class, 'storeVersion'])->name('scorecards.versions.store');
        Route::get('/versi/{version}', [AdminScorecardVersionController::class, 'show'])->name('versions.show');
        Route::post('/versi/{version}/aktifkan', [AdminScorecardVersionController::class, 'activate'])->name('versions.activate');
        Route::post('/versi/{version}/nonaktifkan', [AdminScorecardVersionController::class, 'deactivate'])->name('versions.deactivate');
        Route::delete('/versi/{version}', [AdminScorecardVersionController::class, 'destroy'])->name('versions.destroy');

        Route::get('/scorecard/{scorecard}', [AdminScorecardController::class, 'show'])->name('scorecards.show');
        Route::get('/scorecard/{scorecard}/edit', [AdminScorecardController::class, 'edit'])->name('scorecards.edit');
        Route::put('/scorecard/{scorecard}', [AdminScorecardController::class, 'update'])->name('scorecards.update');
        Route::delete('/scorecard/{scorecard}', [AdminScorecardController::class, 'destroy'])->name('scorecards.destroy');

        // Kategori (isi versi)
        Route::get('/versi/{version}/kategori/tambah', [AdminScorecardCategoryController::class, 'create'])->name('categories.create');
        Route::post('/versi/{version}/kategori', [AdminScorecardCategoryController::class, 'store'])->name('categories.store');
        Route::get('/kategori/{category}/edit', [AdminScorecardCategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/kategori/{category}', [AdminScorecardCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/kategori/{category}', [AdminScorecardCategoryController::class, 'destroy'])->name('categories.destroy');

        // Parameter (isi kategori)
        Route::get('/kategori/{category}/parameter/tambah', [AdminScorecardParameterController::class, 'create'])->name('parameters.create');
        Route::post('/kategori/{category}/parameter', [AdminScorecardParameterController::class, 'store'])->name('parameters.store');
        Route::get('/parameter/{parameter}/edit', [AdminScorecardParameterController::class, 'edit'])->name('parameters.edit');
        Route::put('/parameter/{parameter}', [AdminScorecardParameterController::class, 'update'])->name('parameters.update');
        Route::delete('/parameter/{parameter}', [AdminScorecardParameterController::class, 'destroy'])->name('parameters.destroy');

        /*
        |------------------------------------------------------------------
        | Target Sampling (PRD Employee KPI/QA §17-§18)
        |------------------------------------------------------------------
        |
        | Kebijakan "berapa sample seharusnya diambil" adalah angka pembanding
        | bagi laporan (§39: Target 100 / Completed 87 / Completion 87%).
        | Karenanya ia diperlakukan sebagai MASTER KONFIGURASI: Admin yang
        | mengelola, QA/Supervisor/HR hanya membacanya.
        |
        | Peletakan di grup `admin.kpi.*` bukan sekadar mengikuti folder —
        | dengan begitu navigasi master KPI (Scorecard/KPI/Channel) dapat
        | menambahkan tab "Target Sampling" tanpa rute kedua yang mirip.
        |
        | Parameter rute memakai `{qaTarget}` (bukan `{target}`) karena
        | `target` adalah kata yang sangat umum dan mudah bertabrakan dengan
        | konsep lain di modul ini (mis. target KPI pada PRD §5).
        |
        | AGENT: asisten | 2026-10-04 | tambah grup route admin.kpi.qa-targets.* | modul KPI, fase 3 QA reporting
        */
        Route::prefix('target-sampling')->name('qa-targets.')->group(function () {
            Route::get('/', [AdminQaSamplingTargetController::class, 'index'])->name('index');
            Route::get('/tambah', [AdminQaSamplingTargetController::class, 'create'])->name('create');
            Route::post('/', [AdminQaSamplingTargetController::class, 'store'])->name('store');
            Route::get('/{qaTarget}/edit', [AdminQaSamplingTargetController::class, 'edit'])->name('edit');
            Route::put('/{qaTarget}', [AdminQaSamplingTargetController::class, 'update'])->name('update');
            Route::delete('/{qaTarget}', [AdminQaSamplingTargetController::class, 'destroy'])->name('destroy');
        });
    });

    /*
    |----------------------------------------------------------------------
    | IT Ticketing — Tiket Saya (Employee)
    |----------------------------------------------------------------------
    |
    | Bertanya ke IT adalah hak setiap karyawan, jadi tidak ada gate di sini.
    | Yang membatasi adalah TicketPolicy: user hanya bisa membaca tiket
    | MILIKNYA sendiri (tiket bersifat pribadi antar karyawan). Daftar tiket
    | sendiri memakai `{ticket}` yang diikat ke kolom `ticket_number` (lihat
    | Ticket::getRouteKeyName()) supaya nomor tiket seperti TIK-… langsung
    | bisa dipakai di URL, mengikuti pola `{role:name}` pada menu Role.
    */
    Route::prefix('tiket')->name('tickets.')->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('index');
        Route::get('/baru', [TicketController::class, 'create'])->name('create');
        Route::post('/', [TicketController::class, 'store'])->name('store');
        Route::get('/{ticket}', [TicketController::class, 'show'])->name('show');
        Route::post('/{ticket}/balas', [TicketController::class, 'reply'])->name('reply');
    });

    /*
    |----------------------------------------------------------------------
    | IT Ticketing — Antrean Tim IT
    |----------------------------------------------------------------------
    |
    | Hanya untuk pemegang `ticket.view` (role `it`, dan `admin` sebagai
    | pengelola wadah) — PRD §42. Tiket di sini tampil utuh, termasuk catatan
    | internal. Otorisasi tiap aksi tetap dipegang TicketPolicy agar aturannya
    | satu sumber dan tidak bergantung pada URL.
    */
    Route::prefix('it')->name('it.')->middleware(['can:manage-tickets'])->group(function () {
        Route::get('/', [ItTicketController::class, 'dashboard'])->name('dashboard');

        Route::get('/tiket', [ItTicketController::class, 'index'])->name('tickets.index');
        Route::get('/tiket/{ticket}', [ItTicketController::class, 'show'])->name('tickets.show');
        Route::post('/tiket/{ticket}/tugaskan', [ItTicketController::class, 'assign'])->name('tickets.assign');
        Route::post('/tiket/{ticket}/status', [ItTicketController::class, 'updateStatus'])->name('tickets.status');
        Route::post('/tiket/{ticket}/balas', [ItTicketController::class, 'reply'])->name('tickets.reply');
    });

    /*
    |----------------------------------------------------------------------
    | QA — Content Management
    |----------------------------------------------------------------------
    */
    Route::prefix('qa')->name('qa.')->middleware(['can:manage-information'])->group(function () {
        Route::get('/', [QaDashboardController::class, 'index'])->name('dashboard');

        Route::get('/informasi', [QaInformationController::class, 'index'])->name('information.index');
        Route::get('/informasi/tambah', [QaInformationController::class, 'create'])->name('information.create');
        Route::post('/informasi', [QaInformationController::class, 'store'])->name('information.store');
        Route::get('/informasi/{information}', [QaInformationController::class, 'show'])->name('information.show');
        Route::get('/informasi/{information}/edit', [QaInformationController::class, 'edit'])->name('information.edit');
        Route::put('/informasi/{information}', [QaInformationController::class, 'update'])->name('information.update');
        Route::post('/informasi/{information}/publish', [QaInformationController::class, 'publish'])->name('information.publish');
        Route::post('/informasi/{information}/archive', [QaInformationController::class, 'archive'])->name('information.archive');
        Route::post('/informasi/{information}/versi', [QaInformationController::class, 'storeVersion'])->name('information.version');

        // Tag (PRD item #3 — tag sebelumnya hanya di-sync inline dari form informasi)
        Route::get('/tag', [QaTagController::class, 'index'])->name('tags.index');
        Route::get('/tag/{tag}/edit', [QaTagController::class, 'edit'])->name('tags.edit');
        Route::put('/tag/{tag}', [QaTagController::class, 'update'])->name('tags.update');
        Route::delete('/tag/{tag}', [QaTagController::class, 'destroy'])->name('tags.destroy');
    });

    // Bank soal dan konfigurasi quiz (QA); hasil lintas karyawan dibatasi
    // dengan quiz_result.view, pengerjaan karyawan berada di /quiz.
    //
    // Quiz pilihan ganda adalah ARSIP: sejak 2026-10-16 modul "Quiz" diarahkan
    // ke ujian essay (`qa/assessment/ujian`). Seluruh rute di bawah dijaga
    // saklar `quiz.multiple_choice` — saat mati (default), URL lama pun tidak
    // bisa dibuka, bukan sekadar disembunyikan dari menu.
    // AGENT: asisten | 2026-10-16 | kunci rute quiz dengan saklar fitur | modul Quiz -> Ujian
    Route::prefix('qa/quiz')->name('qa.quiz.')->middleware(['can:quiz.view', 'feature:quiz.multiple_choice'])->group(function () {
        Route::get('/', [QaQuizAttemptController::class, 'dashboard'])->name('dashboard');
        Route::resource('categories', QaQuizQuestionCategoryController::class)->parameters(['categories' => 'category'])->except(['show']);
        Route::resource('questions', QaQuizQuestionBankController::class)->parameters(['questions' => 'question'])->except(['show']);
        Route::resource('templates', QaQuizTemplateController::class)->parameters(['templates' => 'template']);
        Route::get('template/{template}/versi/tambah', [QaQuizVersionController::class, 'create'])->name('versions.create');
        Route::post('template/{template}/versi', [QaQuizVersionController::class, 'store'])->name('versions.store');
        Route::get('versi/{version}', [QaQuizVersionController::class, 'show'])->name('versions.show');
        Route::get('versi/{version}/edit', [QaQuizVersionController::class, 'edit'])->name('versions.edit');
        Route::put('versi/{version}', [QaQuizVersionController::class, 'update'])->name('versions.update');
        Route::post('versi/{version}/publish', [QaQuizVersionController::class, 'publish'])->name('versions.publish');
        Route::get('hasil', [QaQuizAttemptController::class, 'report'])->middleware('can:quiz_result.view')->name('results');
    });

    Route::prefix('quiz')->name('quiz.')->middleware(['can:access-quiz', 'feature:quiz.multiple_choice'])->group(function () {
        Route::get('/', [QaQuizAttemptController::class, 'index'])->name('index');
        Route::get('/riwayat/{attempt}', [QaQuizAttemptController::class, 'show'])->name('attempts.show');
        Route::get('/{template}/mulai', [QaQuizAttemptController::class, 'start'])->middleware('can:quiz.take')->name('start');
        Route::get('/attempt/{attempt}', [QaQuizAttemptController::class, 'take'])->name('attempts.take');
        Route::post('/attempt/{attempt}', [QaQuizAttemptController::class, 'submit'])->name('attempts.submit');
    });
    /*
    |----------------------------------------------------------------------
    | QA Assessment — Penilaian Kualitatif, Bank Soal & Ujian Tertulis
    |----------------------------------------------------------------------
    |
    | PRD QA Assessment §4/§5/§6/§7.
    |
    | Gate `can:access-qa-assessment` membuka area ini untuk QA, Admin,
    | Supervisor, HR, dan karyawan (melihat assessment & mengerjakan ujian
    | miliknya). Otorisasi tiap AKSI tetap dipegang policy masing-masing,
    | sehingga karyawan hanya lolos untuk baris MILIKNYA.
    |
    | Rute statis (`/tambah`) diletakkan sebelum `/{model}` agar tidak
    | tertangkap sebagai ID.
    |
    | AGENT: asisten | 2026-10-08 | tambah grup route qa.assessments.*, qa.exam.*,
    |                     qa.periods.*, qa.agent-assignments.* | modul QA Assessment
    */
    Route::prefix('qa/assessment')->name('qa.')->middleware(['can:access-qa-assessment'])->group(function () {

        // --- Periode QA (PRD §7) ---
        Route::get('periode', [QaPeriodController::class, 'index'])->name('periods.index');
        Route::get('periode/tambah', [QaPeriodController::class, 'create'])->name('periods.create');
        Route::post('periode', [QaPeriodController::class, 'store'])->name('periods.store');
        Route::get('periode/{qaPeriod}', [QaPeriodController::class, 'show'])->name('periods.show');
        Route::get('periode/{qaPeriod}/edit', [QaPeriodController::class, 'edit'])->name('periods.edit');
        Route::put('periode/{qaPeriod}', [QaPeriodController::class, 'update'])->name('periods.update');
        Route::delete('periode/{qaPeriod}', [QaPeriodController::class, 'destroy'])->name('periods.destroy');

        // --- Penugasan QA ke agent (PRD §6) ---
        Route::get('penugasan', [QaAgentAssignmentController::class, 'index'])->name('agent-assignments.index');
        Route::get('penugasan/tambah', [QaAgentAssignmentController::class, 'create'])->name('agent-assignments.create');
        Route::post('penugasan', [QaAgentAssignmentController::class, 'store'])->name('agent-assignments.store');
        Route::get('penugasan/{qaAgentAssignment}', [QaAgentAssignmentController::class, 'show'])->name('agent-assignments.show');
        Route::get('penugasan/{qaAgentAssignment}/edit', [QaAgentAssignmentController::class, 'edit'])->name('agent-assignments.edit');
        Route::put('penugasan/{qaAgentAssignment}', [QaAgentAssignmentController::class, 'update'])->name('agent-assignments.update');
        Route::delete('penugasan/{qaAgentAssignment}', [QaAgentAssignmentController::class, 'destroy'])->name('agent-assignments.destroy');

        // --- Bank soal (PRD §5) ---
        Route::get('bank-soal', [QaExamQuestionBankController::class, 'index'])->name('exam.question-bank.index');
        Route::get('bank-soal/tambah', [QaExamQuestionBankController::class, 'create'])->name('exam.question-bank.create');
        Route::post('bank-soal', [QaExamQuestionBankController::class, 'store'])->name('exam.question-bank.store');
        Route::get('bank-soal/{questionBank}', [QaExamQuestionBankController::class, 'show'])->name('exam.question-bank.show');
        Route::get('bank-soal/{questionBank}/edit', [QaExamQuestionBankController::class, 'edit'])->name('exam.question-bank.edit');
        Route::put('bank-soal/{questionBank}', [QaExamQuestionBankController::class, 'update'])->name('exam.question-bank.update');
        Route::delete('bank-soal/{questionBank}', [QaExamQuestionBankController::class, 'destroy'])->name('exam.question-bank.destroy');

        // --- Form ujian (PRD §5) ---
        Route::get('ujian', [ExamFormController::class, 'index'])->name('exam.forms.index');
        Route::get('ujian/tambah', [ExamFormController::class, 'create'])->name('exam.forms.create');
        Route::post('ujian', [ExamFormController::class, 'store'])->name('exam.forms.store');
        Route::get('ujian/{examForm}', [ExamFormController::class, 'show'])->name('exam.forms.show');
        Route::get('ujian/{examForm}/edit', [ExamFormController::class, 'edit'])->name('exam.forms.edit');
        Route::put('ujian/{examForm}', [ExamFormController::class, 'update'])->name('exam.forms.update');
        Route::delete('ujian/{examForm}', [ExamFormController::class, 'destroy'])->name('exam.forms.destroy');
        Route::post('ujian/{examForm}/publish', [ExamFormController::class, 'publish'])->name('exam.forms.publish');
        // Tarik form terbit kembali ke draft (hanya bila belum ada peserta mengerjakan).
        // AGENT: asisten | 2026-10-20 | tambah rute tarik ke draft | modul QA Assessment
        Route::post('ujian/{examForm}/tarik-draft', [ExamFormController::class, 'unpublish'])->name('exam.forms.unpublish');

        // Soal pada form (snapshot dari bank soal)
        Route::post('ujian/{examForm}/soal', [ExamFormController::class, 'questionStore'])->name('exam.forms.questions.store');
        // Tulis banyak soal baru sekaligus di dalam form (patokan: form).
        // AGENT: asisten | 2026-10-20 | tambah rute tulis soal massal | modul QA Assessment, bank soal
        Route::post('ujian/{examForm}/soal/tulis', [ExamFormController::class, 'questionBulkStore'])->name('exam.forms.questions.bulk');
        Route::put('ujian/{examForm}/soal/{question}', [ExamFormController::class, 'questionUpdate'])->name('exam.forms.questions.update');
        Route::delete('ujian/{examForm}/soal/{question}', [ExamFormController::class, 'questionDestroy'])->name('exam.forms.questions.destroy');

        // Penugasan form ke agent
        Route::post('ujian/{examForm}/peserta', [ExamFormController::class, 'assignmentStore'])->name('exam.forms.assignments.store');
        Route::delete('ujian/{examForm}/peserta/{assignment}', [ExamFormController::class, 'assignmentDestroy'])->name('exam.forms.assignments.destroy');
        Route::post('ujian/{examForm}/peserta/{assignment}/ulang', [ExamFormController::class, 'assignmentRetake'])->name('exam.forms.assignments.retake');

        // Pengerjaan ujian oleh agent
        Route::get('ujian/{assignment}/kerjakan', [QaExamAttemptController::class, 'take'])->name('exam.forms.take');
        Route::post('ujian/{assignment}/jawab', [QaExamAttemptController::class, 'submitAnswer'])->name('exam.forms.submit-answer');
        Route::post('ujian/{assignment}/selesai', [QaExamAttemptController::class, 'finish'])->name('exam.forms.finish');
        Route::get('ujian/{assignment}/hasil', [QaExamAttemptController::class, 'result'])->name('exam.forms.result');

        // Daftar ujian milik agent — titik masuk menu "Quiz Saya" (sejak Quiz
        // pilihan ganda diarsipkan). Diletakkan sebelum `ujian/{examForm}` agar
        // "ujian-saya" tidak pernah tertangkap sebagai ID form.
        // AGENT: asisten | 2026-10-16 | tambah daftar ujian agent | modul Quiz -> Ujian
        Route::get('ujian-saya', [QaExamAttemptController::class, 'myExams'])->name('exam.forms.my');

        // Lembar jawaban kertas: QA mengunggah foto/scan, lalu menandai
        // "dikumpulkan". Berkas disimpan di disk privat dan hanya bisa dibaca
        // lewat rute ber-otorisasi ini.
        // AGENT: asisten | 2026-10-16 | tambah unggah/pratinjau lembar jawaban | modul Quiz -> Ujian
        Route::post('ujian/{assignment}/lembar-jawaban', [QaExamAttemptController::class, 'uploadSheet'])->name('exam.grading.sheet.upload');
        Route::get('ujian/{assignment}/lembar-jawaban', [QaExamAttemptController::class, 'viewSheet'])->name('exam.grading.sheet.show');
        Route::delete('ujian/{assignment}/lembar-jawaban', [QaExamAttemptController::class, 'destroySheet'])->name('exam.grading.sheet.destroy');

        // Hasil ujian dalam bentuk PDF (untuk arsip pembinaan/HR).
        Route::get('ujian/{assignment}/hasil/pdf', [QaExamAttemptController::class, 'resultPdf'])->name('exam.forms.result.pdf');

        // Penilaian QA
        Route::get('penilaian', [QaExamAttemptController::class, 'gradingIndex'])->name('exam.grading.index');
        Route::get('penilaian/{assignment}', [QaExamAttemptController::class, 'gradingShow'])->name('exam.grading.show');
        Route::post('penilaian/{assignment}', [QaExamAttemptController::class, 'gradingStore'])->name('exam.grading.store');
        Route::post('penilaian/{assignment}/publish', [QaExamAttemptController::class, 'publishResult'])->name('exam.grading.publish');

        // QA menandai lembar jawaban sudah dikumpulkan (mode kertas). Ini yang
        // memindahkan status ke `submitted` tanpa menuntut jawaban digital.
        Route::post('penilaian/{assignment}/kumpulkan', [QaExamAttemptController::class, 'markCollected'])->name('exam.grading.collect');

        // --- Kategori penilaian kualitatif (PRD §4).
        // Diletakkan SEBELUM `/{qaAssessment}` agar `kategori` tidak tertangkap
        // sebagai ID assessment oleh route model binding.
        Route::get('kategori', [QaAssessmentCategoryController::class, 'index'])->name('assessment.categories.index');
        Route::get('kategori/tambah', [QaAssessmentCategoryController::class, 'create'])->name('assessment.categories.create');
        Route::post('kategori', [QaAssessmentCategoryController::class, 'store'])->name('assessment.categories.store');
        Route::get('kategori/{qaAssessmentCategory}', [QaAssessmentCategoryController::class, 'show'])->name('assessment.categories.show');
        Route::get('kategori/{qaAssessmentCategory}/edit', [QaAssessmentCategoryController::class, 'edit'])->name('assessment.categories.edit');
        Route::put('kategori/{qaAssessmentCategory}', [QaAssessmentCategoryController::class, 'update'])->name('assessment.categories.update');
        Route::delete('kategori/{qaAssessmentCategory}', [QaAssessmentCategoryController::class, 'destroy'])->name('assessment.categories.destroy');

        // --- Penilaian kualitatif (PRD §4) ---
        Route::get('/', [QaAssessmentController::class, 'index'])->name('assessments.index');
        Route::get('/tambah', [QaAssessmentController::class, 'create'])->name('assessments.create');
        Route::post('/', [QaAssessmentController::class, 'store'])->name('assessments.store');
        Route::get('/{qaAssessment}', [QaAssessmentController::class, 'show'])->name('assessments.show');
        Route::get('/{qaAssessment}/edit', [QaAssessmentController::class, 'edit'])->name('assessments.edit');
        Route::put('/{qaAssessment}', [QaAssessmentController::class, 'update'])->name('assessments.update');
        Route::delete('/{qaAssessment}', [QaAssessmentController::class, 'destroy'])->name('assessments.destroy');
        Route::post('/{qaAssessment}/publish', [QaAssessmentController::class, 'publish'])->name('assessments.publish');

        Route::post('/{qaAssessment}/item', [QaAssessmentController::class, 'itemStore'])->name('assessments.items.store');
        Route::put('/{qaAssessment}/item/{item}', [QaAssessmentController::class, 'itemUpdate'])->name('assessments.items.update');
        Route::delete('/{qaAssessment}/item/{item}', [QaAssessmentController::class, 'itemDestroy'])->name('assessments.items.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | QA Sampling — Penilaian Sample (PRD Employee KPI/QA §14-§16)
    |----------------------------------------------------------------------
    |
    | Gate `can:access-qa-sampling` membuka area ini untuk QA, Admin,
    | Supervisor, HR, dan karyawan (untuk melihat hasil miliknya). Otorisasi
    | tiap AKSI tetap dipegang QaSamplingAssessmentPolicy, sehingga:
    |   - QA                : create/update/submit/review/cancel
    |   - Supervisor/Admin  : review/lock/cancel
    |   - HR                : view saja
    |   - Karyawan (agent)  : view MILIKNYA saja, dan hanya setelah tidak draft
    |
    | Rute statis (`/tambah`) diletakkan sebelum `/{assessment}` agar tidak
    | tertangkap sebagai ID assessment.
    |
    | AGENT: asisten | 2026-10-03 | tambah grup route qa.sampling.* | modul KPI, fase 2 QA sampling
    */
    Route::prefix('qa/sampling')->name('qa.sampling.')->middleware(['can:access-qa-sampling'])->group(function () {
        Route::get('/', [QaSamplingAssessmentController::class, 'index'])->name('index');
        Route::get('/tambah', [QaSamplingAssessmentController::class, 'create'])->name('create');
        Route::post('/', [QaSamplingAssessmentController::class, 'store'])->name('store');

        /*
        |--------------------------------------------------------------
        | Laporan & Analitik (PRD Employee KPI/QA §17/§39-§42)
        |--------------------------------------------------------------
        |
        | Diletakkan SEBELUM `/{assessment}` — kalau tidak, `laporan` akan
        | tertangkap sebagai ID assessment dan halaman laporan tidak pernah
        | tercapai. Ini sumber kesalahan yang sama dengan `/tambah` di atas.
        |
        | Prefix `report` (bukan `laporan`) dipakai agar nama rutenya
        | konsisten dengan istilah teknis yang sudah dipakai service
        | (`QaSamplingReportService`) dan PRD ("Reporting"), meskipun judul
        | halamannya berbahasa Indonesia.
        |
        | Gate `access-qa-sampling` tetap terpasang (karyawan boleh membuka
        | area sampling), tetapi ReportController menolak karyawan lewat
        | `qa_sampling.report.view`, sebab laporan menampilkan data lintas
        | orang (PRD §45).
        |
        | AGENT: asisten | 2026-10-04 | tambah grup route qa.sampling.report.* | modul KPI, fase 3 QA reporting
        */
        Route::prefix('report')->name('report.')->group(function () {
            Route::get('/', [QaSamplingReportController::class, 'index'])->name('index');
            Route::get('/dashboard', [QaSamplingReportController::class, 'dashboard'])->name('dashboard');
            Route::get('/parameter', [QaSamplingReportController::class, 'parameters'])->name('parameters');
            Route::get('/karyawan', [QaSamplingReportController::class, 'employee'])->name('employees.index');
            Route::get('/karyawan/{employee}', [QaSamplingReportController::class, 'employee'])->name('employees.show');

            // Skor QA per agent (rata-rata periode) + ekspor PDF.
            // AGENT: asisten | 2026-10-16 | tambah dashboard skor per agent | modul KPI, skor QA
            Route::get('/skor', [QaSamplingReportController::class, 'scores'])->name('scores');
            Route::get('/skor/pdf', [QaSamplingReportController::class, 'scoresPdf'])->name('scores.pdf');

            // Ekspor PDF laporan assessment (filter di layar ikut terbawa).
            Route::get('/pdf', [QaSamplingReportController::class, 'exportPdf'])->name('pdf');
        });

        Route::get('/{assessment}', [QaSamplingAssessmentController::class, 'show'])->name('show');
        Route::put('/{assessment}', [QaSamplingAssessmentController::class, 'update'])->name('update');
        Route::delete('/{assessment}', [QaSamplingAssessmentController::class, 'destroy'])->name('destroy');

        // Pengisian jawaban (YES/NO/NA) — skor dihitung ulang di server.
        Route::post('/{assessment}/jawaban', [QaSamplingAssessmentController::class, 'answer'])->name('answer');

        // Alur status: draft -> submitted -> reviewed -> locked (+cancelled).
        Route::post('/{assessment}/kirim', [QaSamplingAssessmentController::class, 'submit'])->name('submit');
        Route::post('/{assessment}/periksa', [QaSamplingAssessmentController::class, 'review'])->name('review');
        Route::post('/{assessment}/kunci', [QaSamplingAssessmentController::class, 'lock'])->name('lock');
        Route::post('/{assessment}/batalkan', [QaSamplingAssessmentController::class, 'cancel'])->name('cancel');
    });
});

require __DIR__.'/auth.php';
