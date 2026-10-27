<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\QaSamplingAssessment;
use App\Models\Team;
use App\Models\User;
use App\Services\QaSamplingReportService;
use Illuminate\View\View;

/**
 * Halaman muka HR (PRD Employee KPI/QA §35 "HR: Read-only").
 *
 * AGENT: asisten | 2026-10-20 | buat berkas baru | modul Dashboard per peran
 *
 * HR memakai laporan QA untuk penilaian kinerja LINTAS DIVISI, bukan untuk
 * membina satu orang. Karena itu halaman ini sengaja hanya menampilkan angka
 * per divisi dan per parameter — tidak ada satu pun nama karyawan. Daftar nama
 * beserta skornya sudah ada di laporan QA; menampilkannya lagi di halaman muka
 * hanya memperbesar paparan data pribadi tanpa menambah keputusan yang bisa
 * diambil HR di sini.
 *
 * Cakupan HR adalah seluruh perusahaan (lihat DashboardScope::GLOBAL_ROLES),
 * jadi tidak ada penyaring peran di berkas ini. Yang dijaga di sini adalah
 * BENTUK datanya: agregat, bukan baris per orang.
 */
class DashboardController extends Controller
{
    /** Jendela waktu angka ringkasan. */
    private const WINDOW_DAYS = 30;

    public function __construct(private readonly QaSamplingReportService $reports) {}

    public function index(): View
    {
        $filters = [
            'from' => now()->subDays(self::WINDOW_DAYS)->toDateString(),
            'to' => now()->toDateString(),
        ];

        $summary = $this->reports->summary($filters);
        $statusCounts = $this->reports->statusCounts($filters);

        $aggregates = $this->aggregates($filters);

        $divisions = Department::query()
            ->withCount(['users', 'teams'])
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => $this->row(
                $department->name,
                $department->users_count,
                $department->teams_count,
                $aggregates[$department->id] ?? null,
            ))
            ->values();

        // Assessment tanpa divisi tidak boleh hilang dari tabel: bila
        // dihilangkan, jumlah baris tidak akan sama dengan angka ringkasan di
        // atas dan HR akan mengira datanya rusak.
        $withoutDivision = $this->row(
            'Tanpa divisi',
            User::query()->whereNull('department_id')->count(),
            0,
            $aggregates[0] ?? null,
        );

        if ($withoutDivision['assessments'] > 0) {
            $divisions->push($withoutDivision);
        }

        return view('hr.dashboard', [
            'stats' => [
                'employees' => User::count(),
                'divisions' => Department::count(),
                'teams' => Team::count(),
                'assessments' => $summary['completed'],
                'avg_score' => $summary['avg_score'],
                'zero_tolerance' => $summary['zero_tolerance_count'],
            ],
            'divisions' => $divisions,
            'statusCounts' => $statusCounts,
            'statusLabels' => QaSamplingAssessment::statusLabels(),
            // Parameter yang paling sering gagal — informasi yang bisa
            // ditindaklanjuti lewat pelatihan, tanpa menyebut siapa pun.
            'parameters' => $this->reports->parameterAnalytics($filters, 5),
            'windowDays' => self::WINDOW_DAYS,
        ]);
    }

    /**
     * Agregat assessment per divisi untuk jendela waktu, dikunci per
     * `department_id`. Kunci `0` dipakai untuk baris tanpa divisi.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, object>
     */
    private function aggregates(array $filters): array
    {
        return QaSamplingAssessment::query()
            ->whereIn('status', QaSamplingReportService::COMPLETED_STATUSES)
            ->whereNotNull('final_score')
            ->whereDate('assessment_date', '>=', $filters['from'])
            ->whereDate('assessment_date', '<=', $filters['to'])
            ->selectRaw('department_id, count(*) as total, avg(final_score) as avg_score, sum(zero_tolerance_triggered) as ztp')
            ->groupBy('department_id')
            ->get()
            ->keyBy(fn ($row) => $row->department_id ?? 0)
            ->all();
    }

    /**
     * Satu baris tabel divisi. Tidak memuat nama karyawan — hanya angka.
     *
     * @return array{label: string, employees: int, teams: int, assessments: int, avg_score: ?float, zero_tolerance: int}
     */
    private function row(string $label, int $employees, int $teams, ?object $aggregate): array
    {
        $total = (int) ($aggregate->total ?? 0);

        return [
            'label' => $label,
            'employees' => $employees,
            'teams' => $teams,
            'assessments' => $total,
            'avg_score' => $total > 0 ? round((float) $aggregate->avg_score, 2) : null,
            'zero_tolerance' => (int) ($aggregate->ztp ?? 0),
        ];
    }
}
