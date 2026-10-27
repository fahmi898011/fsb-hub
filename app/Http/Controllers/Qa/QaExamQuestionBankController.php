<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Models\ExamForm;
use App\Models\QaAssessmentCategory;
use App\Models\QaExamQuestionBank;
use App\Services\QaQuestionComposer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Controller bank soal ujian tertulis QA (PRD QA Assessment §5).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class QaExamQuestionBankController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', QaExamQuestionBank::class);

        $questions = QaExamQuestionBank::query()
            ->with(['category', 'forms' => fn ($q) => $q->select('exam_forms.id', 'exam_forms.title')])
            ->when($request->search, fn ($q) => $q->where('question', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->question_type, fn ($q) => $q->where('question_type', $request->question_type))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            // Bank soal adalah kolam bersama: penyaringan "Form" berarti
            // "soal yang dipakai form ini", dan `form_id=none` menampilkan soal
            // yang belum dipakai form mana pun.
            // AGENT: asisten | 2026-10-20 | saring per pemakaian form | modul QA Assessment, bank soal
            ->when($request->form_id === 'none', fn ($q) => $q->unused())
            ->when($request->filled('form_id') && $request->form_id !== 'none',
                fn ($q) => $q->usedByForm((int) $request->form_id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $categories = QaAssessmentCategory::active()->ordered()->get(['id', 'name']);
        $forms = ExamForm::query()->orderBy('title')->get(['id', 'title', 'status']);

        return view('qa.exam.question-bank.index', compact('questions', 'categories', 'forms'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', QaExamQuestionBank::class);

        $categories = QaAssessmentCategory::active()->ordered()->get(['id', 'name']);

        // Bila dibuka dari sebuah form (`?form_id=`), soal langsung ditempelkan
        // ke form itu setelah disimpan — tapi tetap tersimpan di bank soal dan
        // boleh dipakai form lain.
        $targetForm = $this->targetForm($request);

        // Form draft milik QA ini, ditawarkan langsung di halaman tulis soal.
        // Tanpa pilihan ini, soal hanya masuk kolam bank soal dan TIDAK muncul
        // di ujian peserta — sumber kebingungan "soal tidak keluar ke agent".
        // AGENT: asisten | 2026-10-20 | pilih form tujuan di halaman bank | modul QA Assessment
        $forms = ExamForm::query()
            ->where('status', 'draft')
            ->orderBy('title')
            ->get(['id', 'title', 'created_by'])
            ->filter(fn (ExamForm $form) => auth()->user()->can('update', $form))
            ->values();

        return view('qa.exam.question-bank.create', compact('categories', 'targetForm', 'forms'));
    }

    /**
     * Simpan BANYAK soal dari halaman bank soal (satu halaman = banyak soal).
     *
     * Halaman `bank-soal/tambah` menyediakan beberapa baris isian sekaligus.
     * Baris yang dikosongkan dibuang sebelum validasi, baris terisi disimpan
     * sebagai soal bank, lalu ditempel ke form draft tujuan bila halaman
     * dibuka dengan `?form_id=`.
     *
     * AGENT: asisten | 2026-10-20 | tulis soal massal dari bank soal | modul QA Assessment
     */
    public function store(Request $request)
    {
        $this->authorize('create', QaExamQuestionBank::class);

        $targetForm = $this->targetForm($request);

        // Form yang dipilih tapi tidak bisa ditempeli (sudah terbit / bukan
        // milik QA ini) harus ditolak di depan. Kalau dibiarkan, soal tetap
        // tersimpan di bank tetapi diam-diam tidak masuk form mana pun — persis
        // keluhan "soal tidak keluar di ujian agent".
        if ($request->filled('form_id') && ! $targetForm) {
            throw ValidationException::withMessages([
                'form_id' => 'Form tujuan tidak bisa ditambah soal (sudah diterbitkan atau bukan form Anda). Pilih form draft lain.',
            ]);
        }

        // Baris kosong diabaikan: halaman selalu menyiapkan beberapa baris
        // kosong, jadi hanya baris yang benar-benar diisi yang divalidasi.
        $request->merge([
            'questions' => QaQuestionComposer::normalizeRows($request->input('questions', [])),
        ]);

        $data = $request->validate(
            QaQuestionComposer::rules() + [
                'form_id' => 'nullable|exists:exam_forms,id',
                'category_id' => 'nullable|exists:qa_assessment_categories,id',
                'status' => 'nullable|in:active,inactive',
            ],
            QaQuestionComposer::messages()
        );

        $created = DB::transaction(function () use ($data, $targetForm) {
            $questions = QaQuestionComposer::createMany(
                $data['questions'],
                isset($data['category_id']) ? (int) $data['category_id'] : null,
                $data['status'] ?? 'active',
            );

            foreach ($questions as $question) {
                $targetForm?->attachQuestion($question);
            }

            return $questions;
        });

        $count = count($created);

        if ($targetForm) {
            return redirect()
                ->route('qa.exam.forms.show', $targetForm)
                ->with('success', "{$count} soal tersimpan di bank soal dan ditambahkan ke form ini.");
        }

        // Kembali ke halaman tulis soal (bukan ke daftar) supaya QA bisa
        // langsung melanjutkan soal berikutnya tanpa membuka halaman lagi.
        // Pesannya harus jujur: soal ini BELUM dipakai ujian mana pun.
        return redirect()
            ->route('qa.exam.question-bank.create')
            ->with('success', "{$count} soal masuk bank soal, belum masuk form ujian mana pun. Pilih \"Masukkan ke form ujian\" di halaman ini bila soal harus dikerjakan peserta.");
    }

    public function show(QaExamQuestionBank $questionBank)
    {
        $this->authorize('view', $questionBank);

        $questionBank->load('category', 'createdBy', 'forms');

        return view('qa.exam.question-bank.show', compact('questionBank'));
    }

    public function edit(QaExamQuestionBank $questionBank)
    {
        $this->authorize('update', $questionBank);

        $categories = QaAssessmentCategory::active()->ordered()->get(['id', 'name']);

        return view('qa.exam.question-bank.edit', compact('questionBank', 'categories'));
    }

    public function update(Request $request, QaExamQuestionBank $questionBank)
    {
        $this->authorize('update', $questionBank);

        $data = Arr::except($this->validateQuestion($request), ['form_id']);
        $data['updated_by'] = auth()->id();

        $questionBank->update($data);

        return redirect()
            ->route('qa.exam.question-bank.show', $questionBank)
            ->with('success', 'Soal berhasil diperbarui. Form yang memakainya memakai salinan isi soal saat ditempel.');
    }

    public function destroy(QaExamQuestionBank $questionBank)
    {
        $this->authorize('delete', $questionBank);

        // Menghapus soal ikut menghapus barisnya di setiap form (cascade pivot),
        // dan jawaban agent menempel ke baris form itu. Jadi soal yang sudah
        // dipakai form terbit tidak boleh dihapus — cukup dinonaktifkan.
        // AGENT: asisten | 2026-10-20 | jaga soal yang sudah dipakai | modul QA Assessment, bank soal
        $usedByLiveForm = $questionBank->forms()
            ->where('exam_forms.status', '!=', 'draft')
            ->exists();

        if ($usedByLiveForm) {
            return back()->with(
                'error',
                'Soal ini sudah dipakai form yang diterbitkan. Nonaktifkan saja agar jawaban peserta tetap utuh.'
            );
        }

        $questionBank->delete();

        return redirect()
            ->route('qa.exam.question-bank.index')
            ->with('success', 'Soal berhasil dihapus dari bank soal.');
    }

    /**
     * Form draft tujuan bila soal ditulis dari halaman form (`?form_id=`).
     *
     * Form yang sudah terbit tidak boleh diubah soalnya, jadi soal hanya masuk
     * ke bank soal.
     */
    protected function targetForm(Request $request): ?ExamForm
    {
        $formId = (int) $request->input('form_id');

        if ($formId <= 0) {
            return null;
        }

        $form = ExamForm::query()
            ->whereKey($formId)
            ->where('status', 'draft')
            ->first();

        // Hanya pembuat form (policy ExamFormPolicy) yang boleh menambah soal.
        if (! $form || ! auth()->user()->can('update', $form)) {
            return null;
        }

        return $form;
    }

    protected function validateQuestion(Request $request): array
    {
        $data = $request->validate([
            'form_id' => 'nullable|exists:exam_forms,id',
            'category_id' => 'nullable|exists:qa_assessment_categories,id',
            'question_type' => 'required|in:essay,multiple_choice',
            'question' => 'required|string',
            'point' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ]);

        $data['options'] = null;

        if (($data['question_type'] ?? '') === 'multiple_choice') {
            $options = $request->validate([
                'options' => 'required|array|min:2',
                'options.*.text' => 'required|string',
                'options.*.is_correct' => 'boolean',
            ])['options'];

            $labels = range('A', 'Z');
            $data['options'] = collect($options)->values()->map(function ($opt, $i) use ($labels) {
                return [
                    'label' => $labels[$i] ?? '?',
                    'text' => $opt['text'],
                    'is_correct' => (bool) ($opt['is_correct'] ?? false),
                ];
            })->all();

            if (collect($data['options'])->where('is_correct', true)->isEmpty()) {
                abort(422, 'Minimal satu opsi harus ditandai benar.');
            }
        }

        return $data;
    }
}
