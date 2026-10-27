<?php

namespace App\Http\Controllers\Qa\Sampling;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Department;
use App\Models\QaSamplingAssessment;
use App\Models\Scorecard;
use App\Models\User;
use App\Services\DashboardScope;
use App\Services\PdfExportService;
use App\Services\QaSamplingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan & analitik QA Sampling (PRD Employee KPI/QA §17/§39-§42).
 *
 * AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting
 *
 * Berkas ini SENGAJA tipis. Seluruh perhitungan ada di
 * `QaSamplingReportService` yang bebas-HTTP, sementara controller hanya
 * melakukan dua hal:
 *
 * 1. Mengubah query string menjadi array `$filters` yang bersih.
 * 2. Memilih view.
 *
 * Alasannya bukan gaya penulisan, melainkan biaya perawatan: laporan inilah
 * bagian yang paling sering diubah angkanya oleh permintaan bisnis, dan tiap
 * perubahan harus bisa diuji tanpa mensimulasikan request.
 *
 * Batas peran: gate `access-qa-sampling` yang menjaga area ini juga meloloskan
 * KARYAWAN (untuk melihat hasil sendiri, PRD §35). Karena laporan agregat
 * memperlihatkan data lintas karyawan, halaman di sini WAJIB diperiksa lagi
 * dengan `qa_sampling.report.view` (QA/Supervisor/Admin/HR). Karyawan yang
 * menembak URL laporan ditolak 403 — bukan diarahkan ke halaman kosong,
 * supaya batas haknya terlihat jelas.
 */
class ReportController extends Controller
{
    /** Filter yang boleh masuk ke service. Sisanya diabaikan diam-diam. */
    private const FILTER_KEYS = [
        'from', 'to', 'period', 'department_id', 'scorecard_id', 'channel_id',
        'qa_id', 'employee_id', 'status', 'score_min', 'score_max',
    ];

    /**
     * Cakupan data per peran, dihitung sekali per request.
     *
     * Disimpan sebagai properti (bukan dihitung tiap pemanggilan) karena
     * `filters()` dipanggil beberapa kali dalam satu halaman dan tiap
     * perhitungan menjalankan query ke tabel tim.
     */
    private ?DashboardScope $scope = null;

    public function __construct(
        private readonly QaSamplingReportService $reports,
        private readonly PdfExportService $pdf,
    ) {
        $this->middleware(function ($request, $next) {
            // Karyawan boleh MEMBUKA area sampling (gate), tetapi tidak boleh
            // melihat laporan lintas orang. Penolakan di sini, bukan di view,
            // agar tidak ada data yang sempat terhitung.
            abort_unless($request->user()?->can('qa_sampling.report.view'), 403);

            return $next($request);
        });
    }

    /**
     * Dashboard QA (PRD §39): target, completed, remaining, completion,
     * rata-rata skor, assessment minggu ini, dan pending review.
     */
    public function dashboard(Request $request): View
    {
        $filters = $this->filters($request);

        // "Assessment minggu ini" dan "pending review" selalu dihitung dari
        // rentang minggu berjalan — bukan dari filter pengguna. Kalau ikut
        // filter, kedua angka itu berhenti menjadi penanda aktivitas harian
        // dan berubah makna menjadi "dalam rentang yang dipilih".
        //
        // Cakupan peran tetap diterapkan: angka mingguan pun tidak boleh
        // memperlihatkan pekerjaan tim lain ke supervisor.
        $thisWeek = $this->scope()->apply([
            'from' => now()->startOfWeek()->toDateString(),
            'to' => now()->endOfWeek()->toDateString(),
        ]);

        return view('qa.sampling.report.dashboard', [
            'filters' => $filters,
            'scopeNotice' => $this->scope()->notice(),
            'progress' => $this->reports->samplingProgress($filters),
            'summary' => $this->reports->summary($filters),
            'statusCounts' => $this->reports->statusCounts($filters),
            'distribution' => $this->reports->scoreDistribution($filters),
            'trend' => $this->reports->periodTrend($filters),
            'averages' => $this->reports->averages($filters),
            'topParameters' => $this->reports->parameterAnalytics($filters, 5),
            'thisWeek' => [
                'total' => $this->reports->summary($thisWeek)['completed'],
                'pending_review' => $this->reports->statusCounts($this->scope()->apply(['status' => 'submitted']))['submitted'] ?? 0,
            ],
            'options' => $this->options(),
        ]);
    }

    /**
     * Analitik parameter (PRD §41): YES/NO/NA, NO Rate, total & rata-rata
     * penalty per parameter. Halaman yang menjawab "parameter mana yang paling
     * sering salah".
     */
    public function parameters(Request $request): View
    {
        $filters = $this->filters($request);

        return view('qa.sampling.report.parameters', [
            'filters' => $filters,
            'scopeNotice' => $this->scope()->notice(),
            'parameters' => $this->reports->parameterAnalytics($filters, 200),
            'options' => $this->options(),
        ]);
    }

    /**
     * Skor QA per agent — rata-rata periode (BUKAN KPI final).
     *
     * AGENT: asisten | 2026-10-16 | tambah halaman skor per agent | modul KPI, skor QA
     *
     * Halaman ini menjawab pertanyaan yang paling sering muncul di rapat QA:
     * "siapa yang skornya paling rendah bulan ini, dan berapa sampel yang sudah
     * dinilai?" Karena itu isinya tiga lapis:
     *
     * 1. Ringkasan angka (agent dinilai, rata-rata, tertinggi, jumlah sample).
     * 2. Grafik: peringkat, tren per periode, distribusi skor.
     * 3. Tabel lengkap yang bisa dicetak/diekspor.
     *
     * Labelnya sengaja "Skor QA (Rata-rata Periode)", bukan "KPI": mencampur
     * rata-rata sampling dengan KPI akan membuat angka ini dipakai untuk
     * keputusan yang bukan haknya.
     */
    public function scores(Request $request): View
    {
        $filters = $this->filters($request);
        $agents = $this->reports->agentScores($filters);

        return view('qa.sampling.report.scores', [
            'filters' => $filters,
            'scopeNotice' => $this->scope()->notice(),
            'options' => $this->options(),
            'agents' => $agents,
            'summary' => $this->reports->scoreSummary($filters),
            'progress' => $this->reports->samplingProgress($filters),
            'trend' => $this->reports->periodTrend($filters),
            'distribution' => $this->reports->scoreDistribution($filters),
            'topAgents' => $agents->take(10)->values(),
            'bottomAgents' => $agents->sortBy('avg_score')->take(5)->values(),
        ]);
    }

    /** Versi PDF halaman Skor QA (untuk lampiran laporan). */
    public function scoresPdf(Request $request): Response
    {
        $filters = $this->filters($request);
        $agents = $this->reports->agentScores($filters);

        return $this->pdf->download(
            'pdf.qa-score-dashboard',
            [
                'agents' => $agents,
                'summary' => $this->reports->scoreSummary($filters),
                'trend' => $this->reports->periodTrend($filters),
                'filterSummary' => $this->filterSummary($filters),
            ],
            $this->pdf->filename('skor-qa-per-agent'),
            ['title' => 'Skor QA per Agent (Rata-rata Periode)'],
        );
    }

    /**
     * Ekspor PDF laporan assessment sesuai filter di layar.
     *
     * Dibatasi 2.000 baris: laporan PDF ditujukan untuk dibaca/dicetak, bukan
     * untuk memindahkan data. Untuk daftar penuh, CSV tetap tersedia dan tidak
     * dibatasi.
     */
    public function exportPdf(Request $request): Response
    {
        $filters = $this->filters($request);

        $rows = $this->reports->assessmentQuery($filters)
            ->with(['employee:id,name', 'qa:id,name', 'division:id,name'])
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        return $this->pdf->download(
            'pdf.qa-sampling-report',
            [
                'assessments' => $rows,
                'summary' => $this->reports->summary($filters),
                'filterSummary' => $this->filterSummary($filters),
                'truncated' => $rows->count() >= 2000,
            ],
            $this->pdf->filename('laporan-qa-sampling'),
            [
                'title' => 'Laporan QA Sampling',
                'orientation' => 'landscape',
            ],
        );
    }

    /**
     * Ringkasan filter dalam satu baris untuk kepala dokumen PDF.
     *
     * Tanpa ini, laporan cetak kehilangan konteks: pembaca tidak tahu rentang
     * tanggal atau divisi mana yang dipakai saat mencetak.
     *
     * @param  array<string, mixed>  $filters
     */
    private function filterSummary(array $filters): string
    {
        $parts = [
            'Rentang: '.($filters['from'] ?? '—').' s/d '.($filters['to'] ?? '—'),
        ];

        // Dokumen cetak harus menyebut cakupannya. Laporan supervisor yang
        // dicetak tanpa keterangan akan dikira rekap seluruh perusahaan.
        if ($this->scope()->notice() !== null) {
            $parts[] = 'Cakupan: '.$this->scope()->label();
        }

        if (! empty($filters['period'])) {
            $parts[] = 'Periode: '.$filters['period'];
        }

        if (! empty($filters['department_id'])) {
            $parts[] = 'Divisi: '.(Department::whereKey($filters['department_id'])->value('name') ?? '—');
        }

        if (! empty($filters['scorecard_id'])) {
            $parts[] = 'Scorecard: '.(Scorecard::whereKey($filters['scorecard_id'])->value('name') ?? '—');
        }

        if (! empty($filters['status'])) {
            $parts[] = 'Status: '.$filters['status'];
        }

        return implode(' · ', $parts);
    }

    /**
     * Riwayat skor seorang karyawan (PRD §39/§42 "Employee history").
     *
     * Halaman ini untuk QA/Supervisor/HR. Karyawan melihat riwayatnya sendiri
     * di `qa.sampling.index` yang sudah menyaring ke barisnya.
     */
    public function employee(Request $request, ?User $employee = null): View
    {
        $filters = $this->filters($request);

        // Tanpa karyawan terpilih, halaman menampilkan pemilih orang beserta
        // daftar karyawan yang punya hasil — bukan halaman kosong yang terasa
        // rusak.
        $selected = $employee ?? ($filters['employee_id'] ?? null
            ? User::find($filters['employee_id'])
            : null);

        // Cakupan peran juga berlaku pada ORANG yang dipilih, bukan hanya pada
        // baris hasil. Tanpa pemeriksaan ini supervisor bisa membuka riwayat
        // karyawan tim lain lewat URL dan menerima halaman kosong — yang tetap
        // memberi tahu bahwa orang itu ada. Ditolak 403, sama seperti laporan
        // lintas orang lainnya.
        if ($selected !== null && ! $this->scope()->allows($selected->id)) {
            abort(403, 'Riwayat karyawan ini berada di luar cakupan data Anda.');
        }

        $employeeFilters = $selected ? $filters + ['employee_id' => $selected->id] : $filters;

        return view('qa.sampling.report.employee', [
            'filters' => $filters,
            'scopeNotice' => $this->scope()->notice(),
            'employee' => $selected,
            'history' => $selected ? $this->reports->employeeHistory($selected->id, $filters) : collect(),
            'parameters' => $selected ? $this->reports->parameterAnalytics($employeeFilters, 50) : collect(),
            'employees' => $this->employeesWithResults($filters),
            'options' => $this->options(),
        ]);
    }

    /**
     * Laporan QA Assessment berfilter lengkap (PRD §42) + ekspor CSV.
     *
     * Ekspor CSV, bukan Excel, dipilih dengan sadar: satu format ini dibuka
     * Excel/LibreOffice/Sheets tanpa pustaka tambahan, sehingga server tidak
     * perlu memasang penulis spreadsheet hanya untuk mengeluarkan daftar baris.
     */
    public function index(Request $request): View|StreamedResponse
    {
        $filters = $this->filters($request);

        if ($request->query('format') === 'csv') {
            return $this->exportCsv($filters);
        }

        $assessments = $this->reports->assessmentQuery($filters)
            ->with(['employee:id,name', 'qa:id,name', 'division:id,name'])
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('qa.sampling.report.index', [
            'filters' => $filters,
            'scopeNotice' => $this->scope()->notice(),
            'assessments' => $assessments,
            'summary' => $this->reports->summary($filters),
            'options' => $this->options(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Bantu                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Bersihkan query string menjadi array filter.
     *
     * Hanya kunci yang dikenal yang diteruskan: tanpa daftar putih ini, siapa
     * pun bisa menitipkan parameter tak terduga ke dalam query builder.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $filters = [];

        foreach (self::FILTER_KEYS as $key) {
            $value = $request->query($key);

            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }

        // Rentang tanggal default = 30 hari terakhir. Laporan tanpa batas waktu
        // akan diam-diam menghitung SELURUH riwayat, dan angka itu hampir selalu
        // bukan yang dimaksud pembacanya.
        $filters['from'] ??= now()->subDays(30)->toDateString();
        $filters['to'] ??= now()->toDateString();

        // Cakupan peran diterapkan PALING AKHIR: filter pilihan pengguna hanya
        // bisa mempersempit, tidak pernah melebarkan. Supervisor yang memilih
        // divisi lain tetap hanya melihat timnya sendiri.
        return $this->scope()->apply($filters);
    }

    /**
     * Cakupan data pengguna saat ini.
     *
     * Supervisor dibatasi ke tim yang ia pimpin; QA/HR/Admin melihat seluruh
     * perusahaan. Sebelum ini laporan hanya dijaga izin `qa_sampling.report.view`
     * sehingga supervisor — yang izinnya sama — ikut melihat skor seluruh
     * karyawan, padahal halaman penilaian quiz-nya sudah dibatasi ke timnya.
     */
    private function scope(): DashboardScope
    {
        return $this->scope ??= DashboardScope::for(request()->user());
    }

    /**
     * Data untuk seluruh dropdown filter.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'divisions' => Department::orderBy('name')->get(['id', 'name']),
            'scorecards' => Scorecard::orderBy('name')->get(['id', 'name']),
            'channels' => Channel::orderBy('name')->get(['id', 'name']),
            // QA diambil dari yang BENAR-BENAR menilai, bukan seluruh pemegang
            // role QA. Daftar filter yang penuh nama tanpa hasil hanya membuat
            // penyaring terasa lambat dan menyesatkan.
            'qas' => User::query()
                ->whereIn('id', QaSamplingAssessment::query()->whereNotNull('qa_id')->distinct()->select('qa_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'periods' => QaSamplingAssessment::query()
                ->whereNotNull('period')
                ->distinct()
                ->orderByDesc('period')
                ->limit(24)
                ->pluck('period'),
        ];
    }

    /**
     * Karyawan yang punya hasil, untuk pemilih di halaman riwayat.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function employeesWithResults(array $filters): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereIn('id', $this->reports->assessmentQuery($filters)->whereNotNull('employee_id')->select('employee_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Ekspor CSV laporan assessment. Baris disiram bertahap (chunk) supaya
     * ekspor besar tidak menahan seluruh hasil di memori.
     *
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $filters): StreamedResponse
    {
        $filename = 'laporan-qa-sampling-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8: tanpa ini Excel di Windows menampilkan huruf beraksen
            // sebagai karakter aneh, dan laporan yang tampak rusak akan
            // dianggap datanya salah.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Tanggal', 'Periode', 'Karyawan', 'Divisi', 'Scorecard', 'Versi',
                'Channel', 'QA', 'Skor Awal', 'Total Penalti', 'Skor Akhir', 'ZTP', 'Status',
            ]);

            $this->reports->assessmentQuery($filters)
                ->with(['employee:id,name', 'qa:id,name', 'division:id,name'])
                ->orderBy('assessment_date')
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            $row->assessment_date?->format('Y-m-d'),
                            $row->period,
                            $row->employee_name_snapshot ?: $row->employee?->name,
                            $row->division?->name,
                            $row->scorecard_name_snapshot,
                            $row->version_number_snapshot,
                            $row->channel_name_snapshot,
                            $row->qa?->name,
                            $row->initial_score,
                            $row->penalty_total,
                            $row->final_score,
                            $row->zero_tolerance_triggered ? 'Ya' : 'Tidak',
                            $row->status_label,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

