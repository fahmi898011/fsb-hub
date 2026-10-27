<?php

namespace App\Services;

use App\Models\Department;
use App\Models\QaSamplingAssessment;
use App\Models\QaSamplingItem;
use App\Models\QaSamplingTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Mesin agregasi laporan QA Sampling (PRD Employee KPI/QA §39-§42).
 *
 * AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting
 *
 * Kelas ini sengaja TIDAK menyentuh HTTP sama sekali: ia menerima array filter
 * dan mengembalikan angka. Alasannya praktis — laporan paling mudah salah,
 * jadi bagian yang menghitung harus bisa diuji tanpa browser maupun request.
 * Controller hanya bertugas mengubah query string menjadi array filter.
 *
 * Tiga keputusan yang membentuk berkas ini:
 *
 * 1. Agregasi dikerjakan di DATABASE (GROUP BY + SUM), bukan dengan menarik
 *    seluruh baris ke PHP. Report ini akan dipanggil pada ribuan assessment,
 *    dan "hitung di PHP" akan jadi masalah tepat saat datanya mulai berguna.
 *
 * 2. Seluruh laporan membaca dari `qa_sampling_items`/`qa_sampling_assessments`,
 *    yaitu SNAPSHOT - bukan master ScorecardParameter (PRD §11/§38). Dengan
 *    begitu laporan Agustus tetap memakai bobot Agustus walau master telah
 *    diganti.
 *
 * 3. Assessment `cancelled` TIDAK pernah dihitung sebagai hasil; assessment
 *    `draft` belum dihitung sebagai "selesai" karena QA belum menyelesaikannya.
 */
class QaSamplingReportService
{
    /**
     * Status yang dianggap "selesai dikerjakan" (PRD §17 "Completed").
     * Draft belum selesai; cancelled tidak dihitung sama sekali.
     */
    public const COMPLETED_STATUSES = ['submitted', 'reviewed', 'locked'];

    /* ------------------------------------------------------------------ */
    /* Query dasar                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Query assessment yang sudah menerapkan seluruh filter laporan
     * (PRD §42 "QA Assessment Report": Period, Division, Employee, QA,
     * Channel, Scorecard, Score).
     *
     * @param  array<string, mixed>  $filters
     */
    public function assessmentQuery(array $filters): Builder
    {
        return QaSamplingAssessment::query()
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('assessment_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('assessment_date', '<=', $v))
            ->when($filters['period'] ?? null, fn ($q, $v) => $q->where('period', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['scorecard_id'] ?? null, fn ($q, $v) => $q->where('scorecard_id', $v))
            ->when($filters['channel_id'] ?? null, fn ($q, $v) => $q->where('channel_id', $v))
            ->when($filters['qa_id'] ?? null, fn ($q, $v) => $q->where('qa_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            // Cakupan peran dari DashboardScope. Sengaja memakai
            // `array_key_exists`, bukan `when(...)`: array KOSONG harus tetap
            // menjadi `whereIn(..., [])` (nol baris). Kalau diperlakukan sebagai
            // "tidak ada filter", supervisor tanpa tim akan melihat laporan
            // seluruh perusahaan — persis kebocoran yang ingin dicegah.
            ->when(
                array_key_exists('employee_ids', $filters),
                fn ($q) => $q->whereIn('employee_id', (array) $filters['employee_ids']),
            )
            ->when(
                array_key_exists('department_ids', $filters),
                fn ($q) => $q->whereIn('department_id', (array) $filters['department_ids']),
            )
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            // Filter rentang skor (§42). Dipakai untuk mencari kasus terburuk.
            ->when($filters['score_min'] ?? null, fn ($q, $v) => $q->where('final_score', '>=', $v))
            ->when($filters['score_max'] ?? null, fn ($q, $v) => $q->where('final_score', '<=', $v));
    }

    /**
     * Query item (parameter yang dinilai) yang sudah menerapkan filter laporan.
     * Join ke assessments karena hampir semua filter berada di kepala.
     *
     * @param  array<string, mixed>  $filters
     */
    public function itemQuery(array $filters): Builder
    {
        $assessment = $this->assessmentQuery($filters)
            // Hanya assessment final/terkirim yang masuk analitik; draft masih
            // bisa berubah sehingga belum layak jadi statistik (PRD §16).
            ->whereIn('qa_sampling_assessments.status', self::COMPLETED_STATUSES);

        return QaSamplingItem::query()
            ->join('qa_sampling_assessments', 'qa_sampling_assessments.id', '=', 'qa_sampling_items.qa_sampling_assessment_id')
            ->whereIn('qa_sampling_assessments.id', $assessment->select('qa_sampling_assessments.id'));
    }

    /**
     * Jumlah assessment per status (PRD §39, termasuk "Pending Review").
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function statusCounts(array $filters): array
    {
        $counts = $this->assessmentQuery($filters)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $result = [];

        foreach (QaSamplingAssessment::statuses() as $status) {
            $result[$status] = (int) ($counts[$status] ?? 0);
        }

        $result['total'] = array_sum($result);

        return $result;
    }

    /**
     * Skor rata-rata, terendah, dan tertinggi dari assessment yang sudah selesai
     * (PRD §39 "Average Quality Score").
     *
     * @param  array<string, mixed>  $filters
     * @return array{completed:int, avg_score:?float, min_score:?float, max_score:?float, zero_tolerance_count:int}
     */
    public function summary(array $filters): array
    {
        $row = $this->assessmentQuery($filters)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('final_score')
            ->selectRaw('count(*) as completed, avg(final_score) as avg_score, min(final_score) as min_score, max(final_score) as max_score, sum(zero_tolerance_triggered) as ztp')
            ->first();

        $completed = (int) ($row->completed ?? 0);

        return [
            'completed' => $completed,
            'avg_score' => $completed > 0 ? round((float) $row->avg_score, 2) : null,
            'min_score' => $completed > 0 ? round((float) $row->min_score, 2) : null,
            'max_score' => $completed > 0 ? round((float) $row->max_score, 2) : null,
            'zero_tolerance_count' => (int) ($row->ztp ?? 0),
        ];
    }

    /**
     * Distribusi skor akhir dalam empat keranjang (PRD §39 "semua jenis
     * grafik"). Batas 90/80/70 dipilih karena itu ambang yang lazim dipakai QA.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function scoreDistribution(array $filters): array
    {
        $row = $this->assessmentQuery($filters)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('final_score')
            ->selectRaw('
                sum(case when final_score >= 90 then 1 else 0 end) as bucket_a,
                sum(case when final_score >= 80 and final_score < 90 then 1 else 0 end) as bucket_b,
                sum(case when final_score >= 70 and final_score < 80 then 1 else 0 end) as bucket_c,
                sum(case when final_score < 70 then 1 else 0 end) as bucket_d
            ')
            ->first();

        return [
            '90-100' => (int) ($row->bucket_a ?? 0),
            '80-89' => (int) ($row->bucket_b ?? 0),
            '70-79' => (int) ($row->bucket_c ?? 0),
            '<70' => (int) ($row->bucket_d ?? 0),
        ];
    }

    /**
     * Rata-rata skor per scorecard, per QA, per divisi, dan per karyawan
     * (PRD §40/§42).
     *
     * Label diambil dari SNAPSHOT (PRD §11): bila master scorecard berganti
     * nama, laporan lama tetap menampilkan nama saat penilaian berlangsung.
     *
     * @param  array<string, mixed>  $filters
     * @return array{scorecards:Collection<int,array<string,mixed>>,qas:Collection<int,array<string,mixed>>,divisions:Collection<int,array<string,mixed>>,employees:Collection<int,array<string,mixed>>}
     */
    public function averages(array $filters, int $limit = 10): array
    {
        $base = fn () => $this->assessmentQuery($filters)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('final_score');

        $scorecards = $base()
            ->selectRaw('scorecard_name_snapshot as label, count(*) as total, avg(final_score) as avg_score')
            ->groupBy('scorecard_name_snapshot')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'label' => $r->label ?: 'Tanpa nama',
                'total' => (int) $r->total,
                'avg_score' => round((float) $r->avg_score, 2),
            ])->values();

        $qas = $base()
            ->selectRaw('qa_id, count(*) as total, avg(final_score) as avg_score')
            ->groupBy('qa_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'label' => $this->userName($r->qa_id),
                'total' => (int) $r->total,
                'avg_score' => round((float) $r->avg_score, 2),
            ])->values();

        $divisions = $base()
            ->selectRaw('department_id, count(*) as total, avg(final_score) as avg_score')
            ->groupBy('department_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'label' => $this->divisionName($r->department_id),
                'total' => (int) $r->total,
                'avg_score' => round((float) $r->avg_score, 2),
            ])->values();

        $employees = $base()
            ->selectRaw('employee_id, employee_name_snapshot as label, count(*) as total, avg(final_score) as avg_score')
            ->groupBy('employee_id', 'employee_name_snapshot')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'label' => $r->label ?: $this->userName($r->employee_id),
                'employee_id' => $r->employee_id,
                'total' => (int) $r->total,
                'avg_score' => round((float) $r->avg_score, 2),
            ])->values();

        return compact('scorecards', 'qas', 'divisions', 'employees');
    }

    /**
     * Analitik per parameter (PRD §41): jumlah dinilai, jumlah NO, NO Rate,
     * total penalty. Dikelompokkan per NAMA parameter (snapshot), bukan per id,
     * supaya parameter dengan id lama yang sudah dihapus tetap muncul dan
     * supaya versi scorecard berbeda tetap menyatu dalam satu angka.
     *
     * Untuk parameter `inverted` (mis. ZTP "apakah terjadi pelanggaran?"),
     * yang dihitung sebagai pelanggaran adalah jawaban YES; kolomnya tetap
     * `no_count` agar tabel tetap satu bentuk, tetapi maknanya dijelaskan di
     * header tabel UI.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function parameterAnalytics(array $filters, int $limit = 100): Collection
    {
        $rows = $this->itemQuery($filters)
            ->selectRaw("
                parameter_name_snapshot as parameter,
                category_name_snapshot as category,
                count(*) as total,
                sum(case when answer is not null then 1 else 0 end) as answered,
                sum(case when is_violation = 1 then 1 else 0 end) as no_count,
                sum(case when answer = 'na' then 1 else 0 end) as na_count,
                sum(case when answer = 'yes' then 1 else 0 end) as yes_count,
                sum(penalty) as total_penalty
            ")
            ->groupBy('parameter_name_snapshot', 'category_name_snapshot')
            ->orderByDesc('no_count')
            ->limit($limit)
            ->get();

        return $rows->map(function ($r) {
            $answered = (int) $r->answered;
            $noCount = (int) $r->no_count;

            return [
                'parameter' => $r->parameter,
                'category' => $r->category,
                'total' => (int) $r->total,
                'answered' => $answered,
                'yes_count' => (int) $r->yes_count,
                'no_count' => $noCount,
                'na_count' => (int) $r->na_count,
                // NO Rate dihitung atas item yang SUDAH dijawab saja, bukan atas
                // seluruh baris. Bila pembagi ikut menghitung baris kosong,
                // parameter yang jarang muncul akan tampak "aman" hanya karena
                // tidak pernah dinilai.
                'no_rate' => $answered > 0 ? round($noCount / $answered * 100, 2) : 0.0,
                'total_penalty' => round((float) $r->total_penalty, 2),
                'avg_penalty' => $noCount > 0 ? round((float) $r->total_penalty / $noCount, 2) : 0.0,
            ];
        })->values();
    }

    /**
     * Riwayat skor seorang karyawan dari waktu ke waktu (PRD §39 "Employee
     * history" / §19 "Quality KPI Periodik").
     *
     * @return Collection<int, QaSamplingAssessment>
     */
    public function employeeHistory(int $employeeId, array $filters = [], int $limit = 50): Collection
    {
        return $this->assessmentQuery($filters + ['employee_id' => $employeeId])
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->orderBy('assessment_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Laporan progres sampling (PRD §17/§42 "Sampling Report"): Target,
     * Completed, Remaining, Completion %.
     *
     * @param  array<string, mixed>  $filters
     * @return array{target:int, completed:int, remaining:int, completion:float, target_type:string, target_value:int}
     */
    public function samplingProgress(array $filters): array
    {
        $completed = $this->assessmentQuery($filters)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->count();

        $target = $this->resolveTarget($filters);

        $remaining = max(0, $target['value'] - $completed);
        $completion = $target['value'] > 0
            ? round(min(100, $completed / $target['value'] * 100), 2)
            : 0.0;

        return [
            'target' => $target['value'],
            'completed' => $completed,
            'remaining' => $remaining,
            'completion' => $completion,
            'target_type' => $target['type'],
            'target_value' => $target['per_unit'],
        ];
    }

    /**
     * Tren jumlah assessment & rata-rata skor per periode (PRD §19/§42),
     * diurutkan menaik supaya siap dipakai grafik garis.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{period:string, total:int, avg_score:?float}>
     */
    public function periodTrend(array $filters, int $limit = 12): Collection
    {
        return $this->assessmentQuery($filters)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('period')
            ->selectRaw('period, count(*) as total, avg(final_score) as avg_score')
            ->groupBy('period')
            ->orderBy('period')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'period' => (string) $r->period,
                'total' => (int) $r->total,
                'avg_score' => round((float) $r->avg_score, 2),
            ])->values();
    }

    /* ------------------------------------------------------------------ */
    /* Skor QA per agent (bukan KPI final)                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Rata-rata skor per AGENT pada rentang filter, terurut dari tertinggi.
     *
     * AGENT: asisten | 2026-10-16 | tambah agregasi per agent | modul KPI, skor QA
     *
     * Ini BUKAN skor KPI. Formula KPI belum diputuskan tim QA, jadi yang
     * dihitung hanya rata-rata skor sampling yang sudah selesai — angka yang
     * bisa dipertanggungjawabkan apa adanya. Halaman menampilkannya dengan
     * label "Skor QA (Rata-rata Periode)" agar tidak disalahartikan.
     *
     * Target per agent diturunkan dari aturan target yang sama dengan
     * `samplingProgress()`, sehingga angka "Capaian" di tabel per orang tidak
     * bertentangan dengan capaian keseluruhan di dashboard.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{employee_id:int, name:string, division:string, total:int, avg_score:float, min_score:float, max_score:float, target:int, completion:float}>
     */
    public function agentScores(array $filters, int $limit = 200): Collection
    {
        $rows = $this->assessmentQuery($filters)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('final_score')
            ->whereNotNull('employee_id')
            ->selectRaw('
                employee_id,
                count(*) as total,
                avg(final_score) as avg_score,
                min(final_score) as min_score,
                max(final_score) as max_score
            ')
            ->groupBy('employee_id')
            ->orderByDesc('avg_score')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->whereIn('id', $rows->pluck('employee_id'))
            ->get(['id', 'name', 'department_id'])
            ->keyBy('id');

        $divisions = Department::query()
            ->whereIn('id', $users->pluck('department_id')->filter()->unique())
            ->pluck('name', 'id');

        $target = $this->resolveTarget($filters);

        // Target per orang: untuk aturan `total`, target keseluruhan dibagi
        // jumlah karyawan dalam cakupan agar tiap baris punya pembanding yang
        // masuk akal. Untuk aturan lain, `per_unit` memang sudah per orang.
        $perAgent = $target['type'] === 'total'
            ? (int) ceil($target['value'] / max(1, $this->scopeEmployeeCount($filters)))
            : (int) $target['per_unit'];

        return $rows->map(function ($row) use ($users, $divisions, $perAgent) {
            $user = $users->get($row->employee_id);
            $total = (int) $row->total;

            return [
                'employee_id' => (int) $row->employee_id,
                'name' => $user?->name ?? $this->userName($row->employee_id),
                'division' => $divisions->get($user?->department_id) ?? 'Tanpa divisi',
                'total' => $total,
                'avg_score' => round((float) $row->avg_score, 2),
                'min_score' => round((float) $row->min_score, 2),
                'max_score' => round((float) $row->max_score, 2),
                'target' => $perAgent,
                'completion' => $perAgent > 0
                    ? round(min(100, $total / $perAgent * 100), 2)
                    : 0.0,
            ];
        })->values();
    }

    /**
     * Ringkasan untuk halaman Skor QA.
     *
     * @param  array<string, mixed>  $filters
     * @return array{agents:int, assessments:int, avg_score:?float, max_score:?float}
     */
    public function scoreSummary(array $filters): array
    {
        $row = $this->assessmentQuery($filters)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('final_score')
            ->whereNotNull('employee_id')
            ->selectRaw('count(distinct employee_id) as agents, count(*) as assessments, avg(final_score) as avg_score, max(final_score) as max_score')
            ->first();

        $agents = (int) ($row->agents ?? 0);

        return [
            'agents' => $agents,
            'assessments' => (int) ($row->assessments ?? 0),
            'avg_score' => $agents > 0 ? round((float) $row->avg_score, 2) : null,
            'max_score' => $agents > 0 ? round((float) $row->max_score, 2) : null,
        ];
    }

    /**
     * Aturan target yang berlaku untuk cakupan filter (PRD §18).
     *
     * Urutan pencarian dari paling spesifik ke paling umum: (1) cocok divisi
     * DAN scorecard, (2) cocok divisi saja, (3) cocok scorecard saja,
     * (4) aturan umum. Bila tidak ada satu pun, target = 0; UI lalu mengajak
     * admin mengisi aturan, bukan diam-diam menampilkan 0% yang menyesatkan.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rule:?QaSamplingTarget, type:string, per_unit:int, value:int}
     */
    public function resolveTarget(array $filters): array
    {
        $date = $filters['to'] ?? $filters['from'] ?? now()->toDateString();

        $candidates = QaSamplingTarget::query()
            ->active()
            ->effectiveOn($date)
            ->orderByDesc('effective_date')
            ->get();

        $departmentId = $filters['department_id'] ?? null;
        $scorecardId = $filters['scorecard_id'] ?? null;

        $pick = ($departmentId && $scorecardId
                ? $candidates->first(fn ($t) => $t->department_id == $departmentId && $t->scorecard_id == $scorecardId)
                : null)
            ?? ($departmentId
                ? $candidates->first(fn ($t) => $t->department_id == $departmentId && ! $t->scorecard_id)
                : null)
            ?? ($scorecardId
                ? $candidates->first(fn ($t) => ! $t->department_id && $t->scorecard_id == $scorecardId)
                : null)
            ?? $candidates->first(fn ($t) => ! $t->department_id && ! $t->scorecard_id)
            ?? $candidates->first();

        if (! $pick) {
            return ['rule' => null, 'type' => 'per_employee', 'per_unit' => 0, 'value' => 0];
        }

        $perUnit = (int) $pick->target_value;

        // Bila lebih dari satu aturan sama-sama paling spesifik, jangan
        // menjumlahkan diam-diam: pakai yang terbaru dan biarkan admin
        // merapikan. Menjumlah otomatis akan menaikkan "Target" tanpa sebab
        // yang terlihat di layar.
        $value = match ($pick->target_type) {
            'total' => $perUnit * $this->effectiveWeeks($filters),
            default => $perUnit * $this->scopeEmployeeCount($filters),
        };

        return ['rule' => $pick, 'type' => $pick->target_type, 'per_unit' => $perUnit, 'value' => $value];
    }

    /**
     * Jumlah karyawan dalam cakupan filter; dipakai sebagai pengali target
     * per_employee/per_qa/per_team/per_division.
     *
     * @param  array<string, mixed>  $filters
     */
    private function scopeEmployeeCount(array $filters): int
    {
        $query = User::query()
            // Bila cakupan peran sudah menyebut orangnya satu per satu, daftar
            // itulah yang dipakai. Menambah syarat "harus berperan employee"
            // akan mengecilkan target secara diam-diam untuk anggota tim yang
            // perannya bukan employee (mis. leader yang ikut dinilai).
            ->when(
                array_key_exists('employee_ids', $filters),
                fn ($q) => $q->whereIn('id', (array) $filters['employee_ids']),
                fn ($q) => $q->whereHas('roles', fn ($sub) => $sub->where('name', 'employee')),
            )
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v));

        return max(1, $query->count());
    }

    /**
     * Jumlah minggu dalam rentang filter (untuk target `total`). Rentang tanpa
     * tanggal dianggap satu minggu agar angka target tidak membengkak.
     *
     * @param  array<string, mixed>  $filters
     */
    private function effectiveWeeks(array $filters): int
    {
        if (empty($filters['from']) || empty($filters['to'])) {
            return 1;
        }

        $days = Carbon::parse($filters['from'])->diffInDays(Carbon::parse($filters['to'])) + 1;

        return max(1, (int) ceil($days / 7));
    }

    /** Nama user untuk label; baris tanpa penilai tampil jelas, bukan kosong. */
    private function userName(?int $userId): string
    {
        if (! $userId) {
            return 'Tanpa penilai';
        }

        return User::whereKey($userId)->value('name') ?? 'Pengguna dihapus';
    }

    /** Nama divisi; assessment tanpa divisi diberi label eksplisit. */
    private function divisionName(?int $departmentId): string
    {
        if (! $departmentId) {
            return 'Tanpa divisi';
        }

        return Department::whereKey($departmentId)->value('name') ?? 'Divisi dihapus';
    }
}
