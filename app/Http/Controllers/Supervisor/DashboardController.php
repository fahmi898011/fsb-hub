<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\ExamAssignment;
use App\Models\QaSamplingAssessment;
use App\Models\User;
use App\Services\DashboardScope;
use App\Services\QaSamplingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman muka Supervisor (PRD Employee KPI/QA §35).
 *
 * AGENT: asisten | 2026-10-20 | buat berkas baru | modul Dashboard per peran
 *
 * Sebelum ini supervisor masuk ke Beranda yang sama dengan karyawan: daftar
 * informasi terbaru dan dokumen populer. Padahal pekerjaannya berbeda — ia
 * membina tim, jadi yang perlu dilihat lebih dulu adalah siapa di timnya yang
 * skornya turun dan apa yang menunggu diperiksa.
 *
 * Cakupan data TIDAK dihitung di sini, melainkan di `DashboardScope`. Alasannya
 * sama dengan laporan QA: bila halaman ini punya aturan sendiri, cepat atau
 * lambat ia akan berbeda dari halaman laporan, dan perbedaan itulah yang
 * membocorkan data tim lain. Supervisor tanpa tim melihat halaman kosong yang
 * menjelaskan keadaannya — bukan diam-diam melihat seluruh perusahaan.
 */
class DashboardController extends Controller
{
    /** Jendela waktu angka ringkasan. */
    private const WINDOW_DAYS = 30;

    public function __construct(private readonly QaSamplingReportService $reports) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $scope = DashboardScope::for($user);

        // Filter laporan hanya bisa MEMPERSEMPIT; cakupan peran diterapkan
        // paling akhir sehingga tidak bisa dilebarkan dari halaman ini.
        $filters = $scope->apply([
            'from' => now()->subDays(self::WINDOW_DAYS)->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $summary = $this->reports->summary($filters);

        // Assessment yang menunggu diperiksa sengaja TIDAK dibatasi jendela
        // 30 hari: sampel yang terlewat tiga bulan lalu tetap pekerjaan yang
        // belum selesai, dan menyembunyikannya membuat antrean tampak bersih.
        $pendingQuery = $this->reports->assessmentQuery($scope->apply(['status' => 'submitted']));

        $examQueueQuery = ExamAssignment::query()
            ->where('status', 'submitted')
            ->when(
                $scope->employeeIds() !== null,
                fn ($query) => $query->whereIn('agent_id', $scope->employeeIds()),
            );

        $teams = $user->ledTeams()
            ->with('department:id,name')
            ->orderBy('name')
            ->get();

        return view('supervisor.dashboard', [
            'scopeNotice' => $scope->notice(),
            'teams' => $teams,
            'stats' => [
                'members' => $scope->count(),
                'teams' => $teams->count(),
                'assessments' => $summary['completed'],
                'avg_score' => $summary['avg_score'],
                'zero_tolerance' => $summary['zero_tolerance_count'],
                'pending_review' => (clone $pendingQuery)->count(),
                'exam_queue' => (clone $examQueueQuery)->count(),
            ],
            // Diurutkan dari rata-rata TERENDAH: halaman ini untuk pembinaan,
            // jadi yang paling perlu dibina muncul lebih dulu.
            'coaching' => $this->coachingRows($scope, $filters),
            'pending' => $pendingQuery
                ->with('employee:id,name')
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->limit(5)
                ->get(),
            'examQueue' => $examQueueQuery
                ->with(['form:id,title', 'agent:id,name'])
                ->orderBy('completed_at')
                ->orderBy('id')
                ->limit(5)
                ->get(),
            'windowDays' => self::WINDOW_DAYS,
        ]);
    }

    /**
     * Rata-rata skor per anggota tim dalam jendela waktu.
     *
     * Dikelompokkan per `employee_id`, bukan memakai relasi: satu karyawan bisa
     * punya puluhan assessment, dan mengambil semuanya lalu menjumlahkan di PHP
     * membuat halaman makin lambat seiring bertambahnya riwayat.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function coachingRows(DashboardScope $scope, array $filters): \Illuminate\Support\Collection
    {
        $rows = QaSamplingAssessment::query()
            ->whereIn('status', QaSamplingReportService::COMPLETED_STATUSES)
            ->whereNotNull('final_score')
            ->whereDate('assessment_date', '>=', $filters['from'])
            ->whereDate('assessment_date', '<=', $filters['to'])
            ->when(
                $scope->employeeIds() !== null,
                fn ($query) => $query->whereIn('employee_id', $scope->employeeIds()),
            )
            ->selectRaw('employee_id, count(*) as total, avg(final_score) as avg_score, max(assessment_date) as last_date, sum(zero_tolerance_triggered) as ztp')
            ->groupBy('employee_id')
            ->orderBy('avg_score')
            ->get();

        // Nama diambil sekali untuk seluruh baris. Memakai `with('employee')`
        // per baris akan menjadi satu query tambahan per anggota tim.
        $names = User::query()
            ->whereIn('id', $rows->pluck('employee_id'))
            ->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'name' => $names[$row->employee_id] ?? 'Karyawan',
            'total' => (int) $row->total,
            'avg_score' => round((float) $row->avg_score, 2),
            'last_date' => $row->last_date,
            'zero_tolerance' => (int) $row->ztp,
        ])->values();
    }
}
