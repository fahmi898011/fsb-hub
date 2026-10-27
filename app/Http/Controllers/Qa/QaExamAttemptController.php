<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Models\ExamAnswer;
use App\Models\ExamAssignment;
use App\Models\ExamFormQuestion;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Services\DashboardScope;
use App\Services\PdfExportService;
use App\Services\QaAssessmentScoringService;
use App\Services\QaNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller pengerjaan & penilaian ujian tertulis QA (PRD QA Assessment §5).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class QaExamAttemptController extends Controller
{
    public function __construct(
        protected QaAssessmentScoringService $scoring,
        protected PdfExportService $pdf,
        protected AuditLogService $audit,
    ) {}

    /**
     * Halaman pengerjaan ujian untuk agent.
     *
     * Hanya AGENT PEMILIK assignment yang boleh masuk. Sebelumnya QA juga bisa
     * membuka halaman ini lewat izin `view`, dan itu berbahaya: membuka halaman
     * memindahkan status ke `in_progress` seolah-olah QA yang mengerjakan.
     * QA menilai lewat halaman penilaian, bukan halaman ini.
     */
    public function take(ExamAssignment $assignment)
    {
        $user = auth()->user();

        abort_unless(
            $assignment->agent_id === $user->id,
            403,
            'Halaman pengerjaan hanya untuk agent yang ditugaskan.',
        );

        abort_unless(
            in_array($assignment->status, ['assigned', 'in_progress'], true),
            403,
            'Ujian ini sudah dikumpulkan.',
        );

        // Saklar mode pengerjaan: bila dimatikan, ujian hanya dikerjakan di
        // kertas dan QA yang mengunggah lembarnya. Pesannya harus menjelaskan
        // itu, bukan sekadar "403".
        abort_unless(
            Setting::enabled('exam.digital_mode', true),
            403,
            'Mode pengerjaan digital sedang dinonaktifkan. Silakan mengerjakan di kertas.',
        );

        if ($assignment->status === 'assigned') {
            $assignment->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        }

        $assignment->load('form.questions', 'answers');

        return view('qa.exam.forms.take', [
            'assignment' => $assignment,
            'answers' => $assignment->answers->keyBy('question_id'),
            'timeRemaining' => $this->timeRemaining($assignment),
        ]);
    }

    /**
     * Daftar ujian milik agent — pengganti menu "Quiz Saya".
     *
     * AGENT: asisten | 2026-10-16 | tambah daftar ujian agent | modul Quiz -> Ujian
     *
     * Menampilkan SEMUA status (belum dikerjakan sampai sudah dinilai) karena
     * agent perlu tahu mana yang masih menunggu, dan mana yang nilainya sudah
     * keluar. Penyaringan dilakukan di query, bukan di view: baris milik orang
     * lain tidak boleh sempat terbaca.
     */
    public function myExams(Request $request): View
    {
        $assignments = ExamAssignment::query()
            ->with('form')
            ->where('agent_id', auth()->id())
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('qa.exam.forms.my', [
            'assignments' => $assignments,
            'status' => (string) $request->query('status', ''),
        ]);
    }

    /**
     * Simpan satu jawaban (auto-save).
     */
    public function submitAnswer(Request $request, ExamAssignment $assignment)
    {
        $this->authorize('submit', $assignment);

        $data = $request->validate([
            'question_id' => 'required|exists:exam_form_questions,id',
            'answer' => 'nullable',
        ]);

        $question = ExamFormQuestion::findOrFail($data['question_id']);

        if ($question->form_id !== $assignment->form_id) {
            abort(403, 'Soal bukan bagian dari form ini.');
        }

        $answer = is_array($data['answer'])
            ? $data['answer']
            : ['value' => $data['answer']];

        ExamAnswer::updateOrCreate(
            ['assignment_id' => $assignment->id, 'question_id' => $question->id],
            ['answer' => $answer],
        );

        if ($request->wantsJson()) {
            return response()->json(['saved' => true]);
        }

        return back();
    }

    /**
     * Selesaikan pengerjaan.
     */
    public function finish(ExamAssignment $assignment)
    {
        $this->authorize('submit', $assignment);

        if ($assignment->answers()->count() === 0) {
            return back()->with('error', 'Belum ada jawaban yang disimpan.');
        }

        $assignment->update([
            'status' => 'submitted',
            'completed_at' => now(),
        ]);

        // Auto-grade pilihan ganda + hitung angka sementara, TANPA memindahkan
        // status ke `graded`: essay masih harus dinilai QA, dan policy `grade`
        // hanya meloloskan status `submitted`.
        // AGENT: asisten | 2026-10-20 | jangan finalkan sebelum essay dinilai | modul QA Assessment
        $this->scoring->gradeAssignment($assignment->fresh(), false);

        return redirect()
            ->route('qa.exam.forms.result', $assignment)
            ->with('success', 'Ujian selesai. Menunggu penilaian QA.');
    }

    /**
     * Halaman hasil ujian (agent & QA).
     */
    public function result(ExamAssignment $assignment)
    {
        $this->authorize('view', $assignment);

        $assignment->load('form.questions', 'answers.question', 'agent');

        $isAgent = auth()->user()->hasRole('employee');

        return view('qa.exam.forms.result', compact('assignment', 'isAgent'));
    }
    // ===================== Penilaian QA =====================

    public function gradingIndex(Request $request)
    {
        $this->authorize('viewAny', ExamAssignment::class);

        // Cakupan peran (PRD §35). Sebelumnya hanya QA yang disaring (lewat
        // `assigned_by`); supervisor dan HR memakai izin
        // `qa.exam.assignment.view` yang sama sehingga ikut membaca jawaban
        // ujian SELURUH perusahaan. Supervisor hanya boleh melihat anggota
        // timnya — aturan yang sama dengan laporan QA Sampling, diambil dari
        // `DashboardScope` supaya tidak berbeda antar halaman.
        $scope = DashboardScope::for(auth()->user());

        $assignments = ExamAssignment::query()
            ->with(['form', 'agent'])
            ->when(auth()->user()->hasRole('qa'), fn ($q) => $q->where('assigned_by', auth()->id()))
            ->when(
                $scope->employeeIds() !== null,
                fn ($q) => $q->whereIn('agent_id', $scope->employeeIds()),
            )
            ->whereIn('status', ['submitted', 'graded', 'published'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest('completed_at')
            ->paginate(15)
            ->withQueryString();

        return view('qa.exam.grading.index', [
            'assignments' => $assignments,
            'scopeNotice' => $scope->notice(),
        ]);
    }

    public function gradingShow(ExamAssignment $assignment)
    {
        $this->authorize('grade', $assignment);

        $assignment->load('form.questions', 'answers.question', 'agent', 'answerSheetUploadedBy');

        return view('qa.exam.grading.show', compact('assignment'));
    }

    /**
     * QA isi nilai essay, lalu finalisasi skor.
     */
    public function gradingStore(Request $request, ExamAssignment $assignment)
    {
        $this->authorize('grade', $assignment);

        $data = $request->validate([
            'scores' => 'required|array',
            'scores.*.answer_id' => 'required|exists:exam_answers,id',
            'scores.*.score' => 'required|numeric|min:0',
            'scores.*.grader_notes' => 'nullable|string',
        ]);

        foreach ($data['scores'] as $row) {
            $answer = ExamAnswer::where('id', $row['answer_id'])
                ->where('assignment_id', $assignment->id)
                ->firstOrFail();

            $maxPoint = $answer->question->point;
            if ((float) $row['score'] > (float) $maxPoint) {
                return back()->withInput()->with('error', "Skor melebihi poin maksimum ({$maxPoint}).");
            }

            $answer->update([
                'score' => $row['score'],
                'grader_notes' => $row['grader_notes'] ?? null,
                'graded_by' => auth()->id(),
                'graded_at' => now(),
            ]);
        }

        $assignment = $this->scoring->finalizeGrading($assignment->fresh());

        return redirect()
            ->route('qa.exam.grading.show', $assignment)
            ->with('success', 'Penilaian berhasil disimpan.');
    }

    public function publishResult(ExamAssignment $assignment)
    {
        $this->authorize('publish', $assignment);

        if ($assignment->status !== 'graded') {
            return back()->with('error', 'Hanya assignment yang sudah dinilai bisa dipublikasikan.');
        }

        if (! $this->scoring->isFullyGraded($assignment)) {
            return back()->with('error', 'Masih ada jawaban essay yang belum dinilai.');
        }

        $assignment = $this->scoring->publishResult($assignment);

        app(QaNotifier::class)->examResultPublished($assignment);

        return redirect()
            ->route('qa.exam.grading.show', $assignment)
            ->with('success', 'Hasil ujian berhasil dipublikasikan ke agent.');
    }

    /* ------------------------------------------------------------------ */
    /* Lembar jawaban kertas */
    /* ------------------------------------------------------------------ */

    /**
     * QA mengunggah foto/scan lembar jawaban agent.
     *
     * AGENT: asisten | 2026-10-16 | tambah unggah lembar jawaban | modul Quiz -> Ujian
     *
     * Berkas disimpan di disk `local` (privat). Alasannya bukan kerapian:
     * lembar jawaban memuat tulisan tangan karyawan dan, pada banyak kasus,
     * data pelanggan. Berkas di disk `public` dapat diunduh siapa pun yang tahu
     * URL-nya, tanpa login.
     *
     * Berkas lama dihapus saat mengganti, supaya folder tidak menumpuk sampah
     * yang tetap bisa diakses lewat URL lama.
     */
    public function uploadSheet(Request $request, ExamAssignment $assignment): RedirectResponse
    {
        $this->authorize('uploadSheet', $assignment);

        abort_unless(
            Setting::enabled('exam.paper_mode', true),
            403,
            'Unggah lembar jawaban kertas sedang dinonaktifkan.',
        );

        $data = $request->validate([
            'answer_sheet' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [
            'answer_sheet.required' => 'Pilih berkas foto/scan lembar jawaban.',
            'answer_sheet.mimes' => 'Berkas harus berupa JPG, PNG, WEBP, atau PDF.',
            'answer_sheet.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        $file = $data['answer_sheet'];
        $old = $assignment->answer_sheet_path;

        $path = $file->storeAs(
            'exam-answer-sheets/'.$assignment->id,
            'lembar-'.$assignment->id.'-'.now()->format('YmdHis').'.'.$file->extension(),
            'local',
        );

        $assignment->update([
            'answer_sheet_path' => $path,
            'answer_sheet_uploaded_by' => auth()->id(),
            'answer_sheet_uploaded_at' => now(),
        ]);

        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        $this->audit->log('qa.exam.answer-sheet.upload', $assignment, ['answer_sheet_path' => $old], [
            'answer_sheet_path' => $path,
        ]);

        return back()->with('success', 'Lembar jawaban berhasil diunggah.');
    }

    /**
     * Menampilkan berkas lembar jawaban.
     *
     * Otorisasi ada di policy (`viewSheet`), bukan di sini: aturannya memuat
     * saklar `exam.agent_can_view_sheet` yang juga dipakai halaman lain.
     */
    public function viewSheet(ExamAssignment $assignment): Response
    {
        $this->authorize('viewSheet', $assignment);

        $path = $assignment->answer_sheet_path;

        abort_unless($path && Storage::disk('local')->exists($path), 404, 'Lembar jawaban tidak ditemukan.');

        return Storage::disk('local')->response($path);
    }

    /** Hapus lembar jawaban (mis. salah unggah). */
    public function destroySheet(ExamAssignment $assignment): RedirectResponse
    {
        $this->authorize('uploadSheet', $assignment);

        $old = $assignment->answer_sheet_path;

        if ($old) {
            Storage::disk('local')->delete($old);
        }

        $assignment->update([
            'answer_sheet_path' => null,
            'answer_sheet_uploaded_by' => null,
            'answer_sheet_uploaded_at' => null,
        ]);

        $this->audit->log('qa.exam.answer-sheet.delete', $assignment, ['answer_sheet_path' => $old], []);

        return back()->with('success', 'Lembar jawaban dihapus.');
    }

    /**
     * QA menandai ujian sudah dikumpulkan (mode kertas).
     *
     * AGENT: asisten | 2026-10-16 | tambah penandaan dikumpulkan | modul Quiz -> Ujian
     *
     * Ini pengganti tombol "Selesai" milik agent: pada mode kertas agent tidak
     * menekan apa pun di sistem. Tanpa aksi ini, assignment akan tertahan di
     * `in_progress` selamanya dan tidak pernah muncul di antrean penilaian.
     */
    public function markCollected(ExamAssignment $assignment): RedirectResponse
    {
        $this->authorize('markCollected', $assignment);

        $assignment->loadMissing('form.questions');

        // Mode kertas: agent tidak pernah membuka form, jadi tidak ada satu pun
        // baris `exam_answers`. Halaman penilaian hanya merender input skor
        // untuk jawaban yang ADA, sehingga tanpa baris kosong ini QA tidak
        // punya tempat mengisi nilai essay sama sekali. Satu baris per soal,
        // dengan isi kosong.
        foreach ($assignment->form->questions as $question) {
            ExamAnswer::firstOrCreate(
                [
                    'assignment_id' => $assignment->id,
                    'question_id' => $question->id,
                ],
                [
                    'answer' => ['value' => ''],
                ],
            );
        }

        $assignment->update([
            'status' => 'submitted',
            'completed_at' => $assignment->completed_at ?? now(),
        ]);

        // Hitung ulang total & max score TANPA mengubah status (lihat
        // `recalculateTotals`): soal yang belum dinilai dihitung 0, tetapi
        // assignment tetap `submitted` agar QA masih boleh mengisi skor.
        $assignment = $this->scoring->recalculateTotals($assignment->fresh());

        $this->audit->log('qa.exam.mark-collected', $assignment, ['status' => 'in_progress'], [
            'status' => $assignment->status,
            'completed_at' => $assignment->completed_at?->toDateTimeString(),
        ]);

        return back()->with('success', 'Ujian ditandai sudah dikumpulkan. Silakan nilai jawabannya.');
    }

    /* ------------------------------------------------------------------ */
    /* Ekspor */
    /* ------------------------------------------------------------------ */

    /**
     * Hasil ujian dalam bentuk PDF.
     *
     * Dipakai QA/HR untuk arsip pembinaan. Agent juga boleh mengunduh hasilnya
     * sendiri karena `view` sudah membatasi ke assignment miliknya.
     */
    public function resultPdf(ExamAssignment $assignment): Response
    {
        $this->authorize('view', $assignment);

        $assignment->load('form.questions', 'answers', 'agent', 'gradedBy');

        return $this->pdf->download(
            'pdf.exam-result',
            [
                'assignment' => $assignment,
                'answers' => $assignment->answers->keyBy('question_id'),
                'filterSummary' => 'Agent: '.($assignment->agent?->name ?? '—').' · Attempt ke-'.$assignment->attempt_ke,
            ],
            $this->pdf->filename('hasil-ujian-'.$assignment->id),
            ['title' => 'Hasil Ujian: '.($assignment->form?->title ?? '—')],
        );
    }

    protected function timeRemaining(ExamAssignment $assignment): ?int
    {
        $duration = $assignment->form->duration_minutes;

        if (! $duration || ! $assignment->started_at) {
            return null;
        }

        $endsAt = $assignment->started_at->copy()->addMinutes($duration);

        return max(0, (int) now()->diffInSeconds($endsAt, false));
    }
}
