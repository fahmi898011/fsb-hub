<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\ExamAssignment;
use App\Models\ExamForm;
use App\Models\ExamFormQuestion;
use App\Models\QaExamQuestionBank;
use App\Models\Team;
use App\Models\User;
use App\Services\QaQuestionComposer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller form ujian tertulis QA (PRD QA Assessment §5).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class ExamFormController extends Controller
{
    // ===================== Form CRUD =====================

    public function index(Request $request)
    {
        $this->authorize('viewAny', ExamForm::class);

        $forms = ExamForm::query()
            ->withCount('questions')
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('qa.exam.forms.index', compact('forms'));
    }

    public function create()
    {
        $this->authorize('create', ExamForm::class);

        return view('qa.exam.forms.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', ExamForm::class);

        $data = $this->validateForm($request);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $form = ExamForm::create($data);

        return redirect()
            ->route('qa.exam.forms.show', $form)
            ->with('success', 'Form ujian berhasil dibuat. Tambahkan soal dari bank soal.');
    }

    public function show(Request $request, ExamForm $examForm)
    {
        $this->authorize('view', $examForm);

        $examForm->load([
            'questions.questionBank',
            'assignments.agent.division',
            'assignments.agent.team',
        ]);

        // Soal yang bisa diambil: SELURUH bank soal aktif yang belum ada di form
        // ini. Bank soal adalah kolam bersama, jadi soal yang sudah dipakai form
        // lain tetap boleh dipakai di sini.
        // AGENT: asisten | 2026-10-20 | bank soal dipakai lintas form | modul QA Assessment, bank soal
        $bankQuestions = QaExamQuestionBank::query()
            ->with('category')
            ->withCount('formQuestions')
            ->where('status', 'active')
            ->whereNotIn('id', $examForm->questions->pluck('question_bank_id'))
            ->orderBy('question')
            ->get();

        // Kandidat peserta: agent yang belum punya penugasan pada form ini.
        // Filter divisi/tim/pencarian dikerjakan di sisi klien (modal), jadi
        // seluruh kandidat dikirim sekali agar mengetik nama tidak memuat ulang.
        $assignedAgentIds = $examForm->assignments->pluck('agent_id')->unique()->values();

        $candidates = User::role('employee')
            ->with(['division:id,name', 'team:id,name'])
            ->whereNotIn('id', $assignedAgentIds)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id', 'department_id', 'department']);

        $candidatePayload = $candidates->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'employee_id' => $user->employee_id,
            'division_id' => $user->department_id,
            'division' => $user->division?->name ?? $user->department,
            'team_ids' => $user->team->pluck('id')->all(),
            'teams' => $user->team->pluck('name')->implode(', '),
        ])->values();

        $departments = Department::active()->orderBy('name')->get(['id', 'name']);
        $teams = Team::active()->with('department:id,name')->orderBy('name')->get(['id', 'name', 'department_id']);

        $assignmentStats = [
            'total' => $examForm->assignments->count(),
            'not_started' => $examForm->assignments->where('status', 'assigned')->count(),
            'in_progress' => $examForm->assignments->where('status', 'in_progress')->count(),
            'waiting_grade' => $examForm->assignments->where('status', 'submitted')->count(),
            'done' => $examForm->assignments->whereIn('status', ['graded', 'published'])->count(),
        ];

        return view('qa.exam.forms.show', compact(
            'examForm',
            'bankQuestions',
            'candidates',
            'candidatePayload',
            'departments',
            'teams',
            'assignmentStats',
        ));
    }

    public function edit(ExamForm $examForm)
    {
        $this->authorize('update', $examForm);

        return view('qa.exam.forms.edit', compact('examForm'));
    }

    public function update(Request $request, ExamForm $examForm)
    {
        $this->authorize('update', $examForm);

        if ($examForm->status !== 'draft') {
            return back()->with('error', 'Hanya form draft yang bisa diedit.');
        }

        $data = $this->validateForm($request);
        $data['updated_by'] = auth()->id();

        $examForm->update($data);

        return redirect()
            ->route('qa.exam.forms.show', $examForm)
            ->with('success', 'Form ujian berhasil diperbarui.');
    }

    public function destroy(ExamForm $examForm)
    {
        $this->authorize('delete', $examForm);

        if ($examForm->status !== 'draft') {
            return back()->with('error', 'Hanya form draft yang bisa dihapus.');
        }

        $examForm->delete();

        return redirect()
            ->route('qa.exam.forms.index')
            ->with('success', 'Form ujian berhasil dihapus.');
    }

    public function publish(ExamForm $examForm)
    {
        $this->authorize('publish', $examForm);

        if ($examForm->status !== 'draft') {
            return back()->with('error', 'Hanya form draft yang bisa diterbitkan.');
        }
        if ($examForm->questions->count() === 0) {
            return back()->with('error', 'Form harus memiliki minimal 1 soal sebelum diterbitkan.');
        }

        $examForm->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return redirect()
            ->route('qa.exam.forms.show', $examForm)
            ->with('success', 'Form ujian berhasil diterbitkan dan siap di-assign ke agent.');
    }

    /**
     * Tarik form terbit kembali ke draft supaya soalnya bisa diperbaiki.
     *
     * Hanya boleh selama belum ada peserta yang mulai mengerjakan (lihat
     * ExamFormPolicy@unpublish). Peserta yang baru `assigned` belum melihat
     * soal apa pun, jadi aman.
     *
     * AGENT: asisten | 2026-10-20 | tarik form ke draft | modul QA Assessment
     */
    public function unpublish(ExamForm $examForm)
    {
        $this->authorize('unpublish', $examForm);

        $examForm->update([
            'status' => 'draft',
            'published_at' => null,
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('qa.exam.forms.show', $examForm)
            ->with('success', 'Form dikembalikan ke draft. Tambahkan soalnya, lalu terbitkan ulang.');
    }

    protected function validateForm(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'allow_retake' => 'boolean',
            'max_attempts' => 'nullable|integer|min:1',
            'duration_minutes' => 'nullable|integer|min:1',
            'passing_score' => 'nullable|numeric|min:0|max:100',
            'grading_method' => 'nullable|in:highest,latest,average',
        ]);
    }
    // ===================== Soal pada form =====================

    /**
     * Ambil soal yang sudah ada di bank soal ke dalam form.
     *
     * Menerima BANYAK soal sekaligus: `question_bank_ids[]`. Versi lama
     * (`question_bank_id`) tetap diterima agar pemanggil lama tidak rusak.
     * Satu soal bank boleh dipakai banyak form; yang dilewati hanya soal yang
     * sudah ada di form ini.
     *
     * AGENT: asisten | 2026-10-20 | bank soal lintas form | modul QA Assessment, bank soal
     */
    public function questionStore(Request $request, ExamForm $examForm)
    {
        $this->authorize('update', $examForm);

        if ($examForm->status !== 'draft') {
            return back()->with('error', 'Hanya form draft yang bisa diubah soalnya.');
        }

        $data = $request->validate([
            'question_bank_ids' => 'nullable|array',
            'question_bank_ids.*' => 'integer|exists:question_bank,id',
            'question_bank_id' => 'nullable|integer|exists:question_bank,id',
            'point' => 'nullable|integer|min:0',
        ]);

        $ids = collect($data['question_bank_ids'] ?? [])
            ->when($data['question_bank_id'] ?? null, fn ($carry, $single) => $carry->push($single))
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu soal dari bank soal.');
        }

        $attached = 0;
        $skipped = 0;

        DB::transaction(function () use ($examForm, $ids, $data, &$attached, &$skipped) {
            $banks = QaExamQuestionBank::query()->whereIn('id', $ids)->get();

            foreach ($banks as $bank) {
                $alreadyAttached = ExamFormQuestion::query()
                    ->where('form_id', $examForm->id)
                    ->where('question_bank_id', $bank->id)
                    ->exists();

                if ($alreadyAttached) {
                    $skipped++;

                    continue;
                }

                $examForm->attachQuestion($bank, $data['point'] ?? null);
                $attached++;
            }
        });

        return back()->with(
            'success',
            "{$attached} soal ditambahkan ke form."
                .($skipped ? " {$skipped} dilewati (soal itu sudah ada di form ini)." : '')
        );
    }

    /**
     * Tulis BANYAK soal baru langsung di dalam form (satu form banyak soal).
     *
     * Inilah jalur utama penyusunan form: QA tidak perlu membuat soal satu per
     * satu di halaman bank lalu menempelkannya satu per satu. Soal yang ditulis
     * di sini masuk ke bank soal bersama lalu langsung ditempel ke form ini,
     * jadi soal yang sama tetap bisa dipakai form lain.
     *
     * AGENT: asisten | 2026-10-20 | tulis soal massal per form | modul QA Assessment, bank soal
     */
    public function questionBulkStore(Request $request, ExamForm $examForm)
    {
        $this->authorize('update', $examForm);

        if ($examForm->status !== 'draft') {
            return back()->with('error', 'Hanya form draft yang bisa diubah soalnya.');
        }

        // Baris kosong pada form isian massal bukan error: halaman menyediakan
        // tiga baris kosong siap pakai (dan bisa ditambah), jadi baris yang
        // tidak diisi cukup dibuang sebelum validasi.
        // AGENT: asisten | 2026-10-20 | abaikan baris soal kosong | modul QA Assessment
        $request->merge([
            'questions' => QaQuestionComposer::normalizeRows($request->input('questions', [])),
        ]);

        $data = $request->validate(
            QaQuestionComposer::rules(),
            QaQuestionComposer::messages()
        );

        $created = DB::transaction(function () use ($examForm, $data) {
            // Soal ditulis di dalam form tetap masuk bank soal bersama dengan
            // kategori kosong, lalu ditempel ke form ini (satu form banyak soal).
            $questions = QaQuestionComposer::createMany($data['questions']);

            foreach ($questions as $question) {
                $examForm->attachQuestion($question);
            }

            return $questions;
        });

        return back()->with('success', count($created).' soal ditulis ke form ini. Total '.$examForm->questions()->count().' soal.');
    }

    public function questionUpdate(Request $request, ExamForm $examForm, ExamFormQuestion $question)
    {
        $this->authorize('update', $examForm);

        // Satu soal bank bisa dipakai banyak form, jadi pastikan baris yang
        // diubah memang milik form di URL (bukan baris form lain).
        abort_unless((int) $question->form_id === (int) $examForm->id, 404);

        if ($examForm->status !== 'draft') {
            return back()->with('error', 'Hanya form draft yang bisa diubah soalnya.');
        }

        $data = $request->validate([
            'point' => 'required|integer|min:0',
        ]);

        $question->update($data);

        return back()->with('success', 'Poin soal berhasil diperbarui.');
    }

    public function questionDestroy(ExamForm $examForm, ExamFormQuestion $question)
    {
        $this->authorize('update', $examForm);

        abort_unless((int) $question->form_id === (int) $examForm->id, 404);

        if ($examForm->status !== 'draft') {
            return back()->with('error', 'Hanya form draft yang bisa dihapus soalnya.');
        }

        $question->delete();

        return back()->with('success', 'Soal berhasil dihapus dari form.');
    }

    // ===================== Penugasan agent =====================

    /**
     * Tugaskan form ke peserta: per orang, per tim, atau per divisi.
     *
     * Sebelumnya hanya bisa per orang lewat `<select multiple>` tanpa pencarian
     * — untuk 1 tim berisi 12 orang, QA harus Ctrl+klik 12 kali. Sekarang satu
     * pilihan tim/divisi langsung menugaskan seluruh anggotanya.
     *
     * AGENT: asisten | 2026-10-20 | distribusi per tim/divisi | modul QA Assessment, distribusi
     */
    public function assignmentStore(Request $request, ExamForm $examForm)
    {
        $this->authorize('create', ExamAssignment::class);

        if ($examForm->status !== 'published') {
            return back()->with('error', 'Form harus diterbitkan dulu sebelum di-assign.');
        }

        $data = $request->validate([
            'agent_ids' => 'nullable|array',
            'agent_ids.*' => 'integer|exists:users,id',
            'team_id' => 'nullable|integer|exists:teams,id',
            'division_id' => 'nullable|integer|exists:departments,id',
        ]);

        $agentIds = collect($data['agent_ids'] ?? [])
            ->map(fn ($id) => (int) $id);

        $scopeLabel = 'peserta';

        if (! empty($data['team_id'])) {
            $agentIds = $agentIds->merge(
                User::role('employee')
                    ->whereHas('team', fn ($query) => $query->where('teams.id', $data['team_id']))
                    ->pluck('id')
            );
            $scopeLabel = 'anggota tim';
        } elseif (! empty($data['division_id'])) {
            $agentIds = $agentIds->merge(
                User::role('employee')
                    ->where('department_id', $data['division_id'])
                    ->pluck('id')
            );
            $scopeLabel = 'anggota divisi';
        }

        $agentIds = $agentIds->unique()->values();

        if ($agentIds->isEmpty()) {
            return back()->with('error', 'Tidak ada peserta yang bisa ditugaskan. Pilih minimal satu orang, tim, atau divisi.');
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($examForm, $agentIds, &$created, &$skipped) {
            $alreadyAssigned = ExamAssignment::query()
                ->where('form_id', $examForm->id)
                ->pluck('agent_id')
                ->unique();

            foreach ($agentIds as $agentId) {
                if ($alreadyAssigned->contains($agentId)) {
                    $skipped++;

                    continue;
                }

                ExamAssignment::create([
                    'form_id' => $examForm->id,
                    'agent_id' => $agentId,
                    'assigned_by' => auth()->id(),
                    'attempt_ke' => 1,
                    'status' => 'assigned',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
                $created++;
            }
        });

        return back()->with(
            'success',
            "{$created} {$scopeLabel} ditugaskan."
                .($skipped ? " {$skipped} dilewati (sudah ditugaskan)." : '')
        );
    }

    public function assignmentDestroy(ExamForm $examForm, ExamAssignment $assignment)
    {
        $this->authorize('delete', $assignment);

        $assignment->delete();

        return back()->with('success', 'Penugasan berhasil dihapus.');
    }

    public function assignmentRetake(ExamForm $examForm, ExamAssignment $assignment)
    {
        $this->authorize('create', ExamAssignment::class);

        $lastAttempt = ExamAssignment::where('form_id', $examForm->id)
            ->where('agent_id', $assignment->agent_id)
            ->max('attempt_ke');

        if (! $examForm->allow_retake) {
            return back()->with('error', 'Form ini tidak mengizinkan percobaan ulang.');
        }

        if ($examForm->max_attempts && $lastAttempt >= $examForm->max_attempts) {
            return back()->with('error', 'Batas maksimal percobaan sudah tercapai.');
        }

        ExamAssignment::create([
            'form_id' => $examForm->id,
            'agent_id' => $assignment->agent_id,
            'assigned_by' => auth()->id(),
            'attempt_ke' => $lastAttempt + 1,
            'status' => 'assigned',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Percobaan ulang berhasil dibuat.');
    }
}
