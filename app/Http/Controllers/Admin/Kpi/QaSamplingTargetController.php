<?php

namespace App\Http\Controllers\Admin\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\QaSamplingTarget;
use App\Models\Scorecard;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Master Target Sampling (Admin) - PRD Employee KPI/QA §17/§18.
 *
 * AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting
 *
 * Halaman ini menampung "angka pembanding" laporan sampling: berapa sample
 * yang SEHARUSNYA diambil per divisi/scorecard per periode. Tanpa aturan yang
 * tersimpan, dashboard hanya bisa menampilkan capaian tanpa makna.
 *
 * Dua hal yang dijaga ketat di sini:
 *
 * 1. TIDAK ADA penghapusan aturan yang sudah berlaku. Aturan lampau hanya
 *    boleh dinonaktifkan, karena laporan periode itu menghitung capaiannya
 *    dengan aturan tersebut. Menghapusnya berarti mengubah angka historis
 *    (PRD §11/§38).
 *
 * 2. Aturan yang saling menimpa diperiksa sebelum disimpan. Bila dua aturan
 *    sama-sama "generalis" atau sama-sama spesifik untuk skop yang sama dan
 *    tanggal efektif yang sama, laporannya menjadi tidak deterministik:
 *    `resolveTarget()` akan memilih satu secara diam-diam, dan admin tidak
 *    punya cara menebak mana yang menang. Karena itu kombinasi ganda ditolak
 *    di sini dengan pesan yang menjelaskan cara memperbaikinya.
 */
class QaSamplingTargetController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', QaSamplingTarget::class);

        $filters = $request->only(['department_id', 'scorecard_id', 'status']);

        $targets = QaSamplingTarget::query()
            ->with(['division:id,name', 'scorecard:id,name'])
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['scorecard_id'] ?? null, fn ($q, $v) => $q->where('scorecard_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            // Urut dari yang terbaru+terpenting: aturan yang berlaku sekarang
            // harus terbaca lebih dulu daripada arsip aturan lama.
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.kpi.qa-targets.index', [
            'targets' => $targets,
            'filters' => $filters,
            'divisions' => Department::orderBy('name')->get(['id', 'name']),
            'scorecards' => Scorecard::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QaSamplingTarget::class);

        return view('admin.kpi.qa-targets.create', $this->formData(new QaSamplingTarget([
            'frequency' => 'weekly',
            'target_type' => 'per_employee',
            'effective_date' => now()->toDateString(),
            'status' => 'active',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', QaSamplingTarget::class);

        $data = $this->validated($request);
        $this->guardAgainstOverlap($data);

        $target = QaSamplingTarget::create($data);

        $this->audit->log('kpi.sampling_target.create', $target, [], $target->only([
            'department_id', 'scorecard_id', 'frequency', 'target_type', 'target_value', 'effective_date', 'status',
        ]));

        return redirect()
            ->route('admin.kpi.qa-targets.index')
            ->with('success', 'Aturan target sampling berhasil ditambahkan.');
    }

    public function edit(QaSamplingTarget $qaTarget): View
    {
        $this->authorize('update', $qaTarget);

        return view('admin.kpi.qa-targets.edit', $this->formData($qaTarget) + ['target' => $qaTarget]);
    }

    public function update(Request $request, QaSamplingTarget $qaTarget): RedirectResponse
    {
        $this->authorize('update', $qaTarget);

        $data = $this->validated($request);
        $this->guardAgainstOverlap($data, $qaTarget);

        $before = $qaTarget->only([
            'department_id', 'scorecard_id', 'frequency', 'target_type', 'target_value', 'effective_date', 'status',
        ]);

        $qaTarget->update($data);

        $this->audit->log('kpi.sampling_target.update', $qaTarget, $before, $qaTarget->only([
            'department_id', 'scorecard_id', 'frequency', 'target_type', 'target_value', 'effective_date', 'status',
        ]));

        return redirect()
            ->route('admin.kpi.qa-targets.index')
            ->with('success', 'Aturan target sampling berhasil diperbarui.');
    }

    /**
     * Hapus hanya untuk aturan yang BELUM berlaku. Aturan yang sudah (atau
     * sedang) berlaku dinonaktifkan, bukan dihapus — lihat QaSamplingTargetPolicy
     * dan penjelasan di docblock kelas.
     */
    public function destroy(QaSamplingTarget $qaTarget): RedirectResponse
    {
        $this->authorize('delete', $qaTarget);

        $snapshot = $qaTarget->only(['department_id', 'scorecard_id', 'target_value', 'effective_date']);

        $qaTarget->delete();

        $this->audit->log('kpi.sampling_target.delete', null, $snapshot, []);

        return redirect()
            ->route('admin.kpi.qa-targets.index')
            ->with('success', 'Aturan target sampling berhasil dihapus.');
    }

    /* ------------------------------------------------------------------ */
    /* Bantu                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'scorecard_id' => ['nullable', 'integer', 'exists:scorecards,id'],
            'frequency' => ['required', Rule::in(QaSamplingTarget::frequencies())],
            'target_type' => ['required', Rule::in(QaSamplingTarget::targetTypes())],
            'target_value' => ['required', 'integer', 'min:1', 'max:10000'],
            'effective_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'target_value.min' => 'Target minimal 1 sample. Target 0 akan membuat laporan selalu 0%.',
        ]);

        // Normalisasi: dropdown "semua" mengirim string kosong, bukan null.
        // Tanpa ini kolom FK akan mencoba menyimpan '' dan gagal di MySQL ketat.
        foreach (['department_id', 'scorecard_id'] as $key) {
            $data[$key] = ($data[$key] ?? null) === '' ? null : ($data[$key] ?? null);
        }

        return $data;
    }

    /**
     * Tolak kombinasi aturan yang saling menimpa pada skop + tanggal yang sama.
     *
     * "Skop" di sini adalah pasangan (department_id, scorecard_id) yang
     * keduanya dibandingkan sebagai NULL/isi, bukan sebagai angka. Dua aturan
     * dianggap menimpa bila skop dan `effective_date`-nya identik, karena pada
     * titik itu `resolveTarget()` tidak lagi punya dasar objektif untuk memilih.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardAgainstOverlap(array $data, ?QaSamplingTarget $ignore = null): void
    {
        $query = QaSamplingTarget::query()
            ->whereDate('effective_date', $data['effective_date'])
            ->whereKeyNot($ignore?->getKey() ?? 0);

        foreach (['department_id', 'scorecard_id'] as $column) {
            $value = $data[$column] ?? null;

            $value === null
                ? $query->whereNull($column)
                : $query->where($column, $value);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'effective_date' => 'Sudah ada aturan dengan divisi, scorecard, dan tanggal mulai yang sama. '
                    .'Ubah salah satunya (mis. beda skop atau tanggal efektif) agar laporan tidak ambigu.',
            ]);
        }
    }

    /**
     * Data pendukung form: daftar pilihan + metadata untuk tampilan.
     *
     * @return array<string, mixed>
     */
    private function formData(QaSamplingTarget $target): array
    {
        return [
            'target' => $target,
            'divisions' => Department::orderBy('name')->get(['id', 'name']),
            'scorecards' => Scorecard::orderBy('name')->get(['id', 'name']),
        ];
    }
}

