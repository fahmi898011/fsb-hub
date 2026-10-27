<?php

namespace App\Http\Controllers\Qa\Sampling;

use App\Http\Controllers\Controller;
use App\Models\QaSamplingAssessment;
use App\Models\QaSamplingItem;
use App\Models\ScorecardVersion;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\QaSamplingNotifier;
use App\Services\QaSamplingScoringService;
use App\Services\QaSamplingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * QA Sampling - daftar, pengisian, dan alur status assessment
 * (PRD Employee KPI/QA §14-§16/§43/§44).
 *
 * AGENT: asisten | 2026-10-03 | buat berkas baru | modul KPI, fase 2 QA sampling
 * AGENT: asisten | 2026-10-03 | ubah: answer() bercabang JSON agar pengisian YES/NO/NA tidak reload halaman | modul KPI, fase 2 QA sampling
 *
 * Alur kerjanya mengikuti urutan yang benar-benar dipakai QA di lapangan
 * (PRD §14): ambil sample dari dashboard sumber, pilih karyawan + scorecard,
 * tempel tautan sample, nilai tiap parameter (YES/NO/NA), lalu submit.
 *
 * Dua penjagaan yang menjadi inti berkas ini:
 *
 * 1. Skor tidak pernah diterima dari frontend (PRD §10/§45). Halaman hanya
 *    mengirim JAWABAN; skor dihitung ulang di server oleh
 *    QaSamplingScoringService setelah setiap perubahan jawaban.
 *
 * 2. Isi assessment hanya dapat diubah selama DRAFT (PRD §16). Penjagaan itu
 *    ada di policy, dan controller memeriksanya lewat `authorize()` sebelum
 *    menulis apa pun.
 */
class AssessmentController extends Controller
{
    public function __construct(
        private readonly QaSamplingService $sampling,
        private readonly QaSamplingScoringService $scoring,
        private readonly AuditLogService $audit,
        private readonly QaSamplingNotifier $notifier,
    ) {}

    /* ------------------------------------------------------------------ */
    /* Daftar                                                              */
    /* ------------------------------------------------------------------ */

    public function index(Request $request): View
    {
        $this->authorize('viewAny', QaSamplingAssessment::class);

        $filters = $request->only(['search', 'status', 'period', 'from', 'to']);
        $canSeeAll = Auth::user()->can('qa_sampling.view');

        $assessments = QaSamplingAssessment::query()
            ->with(['employee:id,name', 'qa:id,name', 'channel:id,name'])
            // Karyawan hanya melihat penilaian DIRINYA dan hanya yang sudah
            // dikirim (draft masih milik QA - PRD §45).
            ->when(! $canSeeAll, function ($query) {
                $query->where('employee_id', Auth::id())
                    ->where('status', '!=', 'draft');
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('sample_id', 'like', "%{$search}%")
                        ->orWhere('sample_link', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['period'] ?? null, fn ($query, $period) => $query->where('period', $period))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('assessment_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('assessment_date', '<=', $to))
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('qa.sampling.index', [
            'assessments' => $assessments,
            'filters' => $filters,
            'canSeeAll' => $canSeeAll,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Membuat                                                             */
    /* ------------------------------------------------------------------ */

    public function create(): View
    {
        $this->authorize('create', QaSamplingAssessment::class);

        return view('qa.sampling.create', [
            'employees' => $this->employeeOptions(),
            'versions' => $this->activeVersionOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', QaSamplingAssessment::class);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'scorecard_version_id' => ['required', 'integer', Rule::exists('scorecard_versions', 'id')],
            'sample_link' => ['nullable', 'string', 'max:2048'],
            'sample_id' => ['nullable', 'string', 'max:255'],
            'assessment_date' => ['required', 'date'],
            'period' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'employee_id.required' => 'Pilih karyawan yang dinilai.',
            'scorecard_version_id.required' => 'Pilih versi scorecard yang dipakai.',
        ]);

        // Versi harus AKTIF: bobot yang tidak berlaku tidak boleh dipakai
        // menilai (PRD §14 langkah 5).
        $version = ScorecardVersion::findOrFail($data['scorecard_version_id']);

        if ($version->status !== 'active') {
            return back()
                ->withInput()
                ->withErrors(['scorecard_version_id' => 'Hanya versi scorecard yang aktif dapat dipakai menilai.']);
        }

        if ($error = $this->duplicateSampleError($data['sample_id'] ?? null)) {
            return back()->withInput()->withErrors(['sample_id' => $error]);
        }

        $assessment = $this->sampling->createAssessment($data, Auth::user());

        $this->audit->log('qa_sampling.assessment.create', $assessment, [], [
            'employee' => $assessment->employee_name_snapshot,
            'scorecard' => $assessment->scorecard_name_snapshot,
            'version' => $assessment->version_number_snapshot,
            'sample_id' => $assessment->sample_id,
        ]);

        return redirect()
            ->route('qa.sampling.show', $assessment)
            ->with('success', 'Assessment berhasil dibuat. Nilai setiap parameter lalu kirim.');
    }

    /* ------------------------------------------------------------------ */
    /* Detail & pengisian                                                  */
    /* ------------------------------------------------------------------ */

    public function show(QaSamplingAssessment $assessment): View
    {
        $this->authorize('view', $assessment);

        $assessment->load(['employee:id,name', 'qa:id,name', 'division:id,name', 'channel:id,name', 'reviewer:id,name']);

        // Dikelompokkan per kategori supaya halaman terbaca seperti form QA
        // (PRD §43), bukan daftar datar.
        $items = $assessment->items()->get()->groupBy('category_name_snapshot');

        return view('qa.sampling.show', [
            'assessment' => $assessment,
            'itemsByCategory' => $items,
            'editable' => $assessment->isEditable() && Auth::user()->can('update', $assessment),
        ]);
    }

    /**
     * Perbarui data header assessment (tautan sample, tanggal, periode,
     * catatan). Hanya berlaku selama DRAFT.
     */
    public function update(Request $request, QaSamplingAssessment $assessment): RedirectResponse
    {
        $this->authorize('update', $assessment);

        $data = $request->validate([
            'sample_link' => ['nullable', 'string', 'max:2048'],
            'sample_id' => ['nullable', 'string', 'max:255'],
            'assessment_date' => ['required', 'date'],
            'period' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($error = $this->duplicateSampleError($data['sample_id'] ?? null, $assessment)) {
            return back()->withInput()->withErrors(['sample_id' => $error]);
        }

        $before = $assessment->only(['sample_link', 'sample_id', 'assessment_date', 'period']);

        $assessment->update($data + ['updated_by' => Auth::id()]);

        $this->audit->log('qa_sampling.assessment.update', $assessment, $before, [
            'sample_link' => $assessment->sample_link,
            'sample_id' => $assessment->sample_id,
            'assessment_date' => $assessment->assessment_date?->toDateString(),
            'period' => $assessment->period,
        ]);

        return back()->with('success', 'Data assessment diperbarui.');
    }

    /**
     * Simpan satu jawaban parameter.
     *
     * Inilah jalur yang menggantikan "pengurangan skor" gaya lama (PRD §10):
     * jawaban disimpan, lalu skor item DAN skor akhir dihitung ulang dari
     * seluruh jawaban. Frontend boleh menampilkan skor real-time, tetapi angka
     * yang tersimpan selalu berasal dari server (PRD §44/§45).
     */
    public function answer(Request $request, QaSamplingAssessment $assessment): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $assessment);

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'answer' => ['nullable', Rule::in(QaSamplingItem::answers())],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var QaSamplingItem|null $item */
        $item = $assessment->items()->whereKey($data['item_id'])->first();

        if (! $item) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Parameter tidak ditemukan pada assessment ini.'], 404);
            }

            return back()->with('error', 'Parameter tidak ditemukan pada assessment ini.');
        }

        $answer = $data['answer'] ?? null;

        $before = [
            'parameter' => $item->parameter_name_snapshot,
            'answer' => $item->answer,
            'penalty' => (float) $item->penalty,
        ];

        /** @var array{penalty_total: float, final_score: float, zero_tolerance_triggered: bool, scored_count: int} $summary */
        $summary = [];

        DB::transaction(function () use ($item, $answer, $data, $assessment, &$summary) {
            $this->scoring->recalculateItem($item, $answer);

            if (array_key_exists('comment', $data)) {
                $item->forceFill(['comment' => $data['comment']])->save();
            }

            $this->scoring->recalculateAssessment($assessment);
            $summary = $this->scoring->summarize($assessment->items()->get());
        });

        // Perubahan hasil assessment WAJIB tercatat (PRD §36).
        $this->audit->log('qa_sampling.answer.update', $assessment, $before, [
            'parameter' => $item->parameter_name_snapshot,
            'answer' => $item->answer,
            'penalty' => (float) $item->penalty,
            'final_score' => (float) $assessment->final_score,
        ]);

        // Jalur AJAX (pengisian cepat): kembalikan hasil supaya halaman TIDAK
        // perlu reload dan posisi gulir QA tidak melompat ke atas.
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'item' => $this->itemPayload($item),
                'summary' => $this->summaryPayload($assessment, $summary),
            ]);
        }

        // Fallback tanpa JavaScript: muat ulang halaman, tetapi langsung
        // menggulir ke parameter yang baru dijawab (#item-<id>) supaya QA tidak
        // kehilangan posisi kerjanya.
        return back()
            ->withFragment('item-'.$item->id)
            ->with('success', "Jawaban {$item->parameter_name_snapshot} tersimpan.");
    }

    /**
     * Bentuk data satu item untuk balasan JSON (dipakai tombol jawaban).
     *
     * Sengaja HANYA mengirim apa yang dibutuhkan tampilan; skor adalah hasil
     * hitung ulang server, bukan angka yang dikirim dari browser (PRD §10).
     *
     * @return array<string, mixed>
     */
    private function itemPayload(QaSamplingItem $item): array
    {
        return [
            'id' => $item->id,
            'answer' => $item->answer,
            'is_violation' => (bool) $item->is_violation,
            'penalty' => (float) $item->penalty,
            'zero_score' => $item->isZeroToleranceScoreZero() && $item->is_violation,
        ];
    }

    /**
     * Bentuk data ringkasan skor untuk balasan JSON (PRD §43/§44).
     *
     * @param  array{penalty_total: float, final_score: float, zero_tolerance_triggered: bool, scored_count: int}  $summary
     * @return array<string, mixed>
     */
    private function summaryPayload(QaSamplingAssessment $assessment, array $summary): array
    {
        return [
            'initial_score' => round((float) $assessment->initial_score, 2),
            'penalty_total' => round((float) $summary['penalty_total'], 2),
            'final_score' => round((float) $summary['final_score'], 2),
            'zero_tolerance_triggered' => (bool) $summary['zero_tolerance_triggered'],
            'scored' => $assessment->items()->whereNotNull('answer')->count(),
            'total' => $assessment->items()->count(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Alur status                                                         */
    /* ------------------------------------------------------------------ */

    public function submit(QaSamplingAssessment $assessment): RedirectResponse
    {
        $this->authorize('submit', $assessment);

        // PRD §46: tidak boleh submit bila parameter wajib belum dinilai.
        $missing = $this->sampling->missingRequiredAnswers($assessment);

        if ($missing !== []) {
            return back()->with('error', 'Masih ada parameter wajib yang belum dinilai: '
                . implode(', ', array_slice($missing, 0, 5))
                . (count($missing) > 5 ? ', dan lainnya.' : '.'));
        }

        // Hitung ulang sekali lagi sebelum mengirim, agar angka yang
        // diserahkan pasti berasal dari jawaban yang tersimpan (§10).
        $this->scoring->recalculateAssessment($assessment);

        $assessment->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        $this->audit->log('qa_sampling.assessment.submit', $assessment, ['status' => 'draft'], [
            'status' => 'submitted',
            'final_score' => (float) $assessment->final_score,
        ]);

        $this->notifier->submitted($assessment);

        return back()->with('success', 'Assessment dikirim untuk diperiksa.');
    }

    public function review(QaSamplingAssessment $assessment): RedirectResponse
    {
        $this->authorize('review', $assessment);

        $assessment->update([
            'status' => 'reviewed',
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->audit->log('qa_sampling.assessment.review', $assessment, ['status' => 'submitted'], [
            'status' => 'reviewed',
            'reviewed_by' => Auth::id(),
        ]);

        $this->notifier->reviewed($assessment, Auth::user());

        return back()->with('success', 'Assessment ditandai sudah diperiksa.');
    }

    public function lock(QaSamplingAssessment $assessment): RedirectResponse
    {
        $this->authorize('lock', $assessment);

        $assessment->update([
            'status' => 'locked',
            'locked_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        $this->audit->log('qa_sampling.assessment.lock', $assessment, ['status' => 'reviewed'], [
            'status' => 'locked',
        ]);

        $this->notifier->locked($assessment);

        return back()->with('success', 'Assessment dikunci. Perubahan normal tidak lagi diizinkan.');
    }

    public function cancel(Request $request, QaSamplingAssessment $assessment): RedirectResponse
    {
        $this->authorize('cancel', $assessment);

        // Batal tanpa alasan tidak dapat ditelusuri; PRD §16 mensyaratkannya.
        $data = $request->validate([
            'cancelled_reason' => ['required', 'string', 'max:1000'],
        ], [
            'cancelled_reason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        $before = ['status' => $assessment->status];

        $assessment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $data['cancelled_reason'],
            'updated_by' => Auth::id(),
        ]);

        $this->audit->log('qa_sampling.assessment.cancel', $assessment, $before, [
            'status' => 'cancelled',
            'reason' => $data['cancelled_reason'],
        ]);

        return back()->with('success', 'Assessment dibatalkan.');
    }

    public function destroy(QaSamplingAssessment $assessment): RedirectResponse
    {
        $this->authorize('delete', $assessment);

        $snapshot = [
            'employee' => $assessment->employee_name_snapshot,
            'sample_id' => $assessment->sample_id,
        ];

        $assessment->delete();

        $this->audit->log('qa_sampling.assessment.delete', null, $snapshot, []);

        return redirect()
            ->route('qa.sampling.index')
            ->with('success', 'Draft assessment berhasil dihapus.');
    }

    /* ------------------------------------------------------------------ */
    /* Internal                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Cek sample ganda (PRD §48 butir 7 dari daftar Open Questions).
     *
     * Assessment yang CANCELLED tidak dihitung, sebab penilaian itu gugur dan
     * sample-nya wajar dinilai ulang.
     */
    private function duplicateSampleError(?string $sampleId, ?QaSamplingAssessment $ignore = null): ?string
    {
        if (empty($sampleId)) {
            return null;
        }

        $exists = QaSamplingAssessment::query()
            ->where('sample_id', $sampleId)
            ->where('status', '!=', 'cancelled')
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        return $exists ? 'Sample dengan ID ini sudah pernah dinilai.' : null;
    }

    /**
     * Karyawan yang dapat dinilai. Dibatasi pada akun ber-role `employee`
     * (agent) supaya QA tidak menilai sesama QA/Admin karena salah pilih.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function employeeOptions()
    {
        return User::role('employee')
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);
    }

    /**
     * Versi scorecard yang boleh dipakai: hanya yang AKTIF.
     *
     * @return \Illuminate\Support\Collection<int, ScorecardVersion>
     */
    private function activeVersionOptions()
    {
        return ScorecardVersion::query()
            ->with(['scorecard:id,name,channel_id', 'scorecard.channel:id,name'])
            ->where('status', 'active')
            ->get()
            ->sortBy(fn (ScorecardVersion $version) => $version->scorecard?->name)
            ->values();
    }
}
