<?php

namespace App\Http\Controllers\Qa\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\QuestionBank;
use App\Models\QuestionCategory;
use App\Models\QuizVersionQuestion;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bank soal (PRD Employee KPI/QA §21/§32) — dikelola QA.
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Bank soal adalah SUMBER soal. Ia tidak dipakai langsung oleh quiz; quiz
 * memakai salinannya lewat versi (PRD §23). Karena itu menyunting soal di sini
 * TIDAK mengubah quiz yang sudah terbit — perilaku ini disengaja dan dijelaskan
 * pada halaman agar QA tidak mengira perubahannya "tidak jalan".
 *
 * Penulisan soal + opsi dilakukan dalam satu transaksi, supaya tidak pernah ada
 * soal tanpa opsi (soal tanpa opsi tidak dapat dinilai).
 */
class QuestionBankController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', QuestionBank::class);

        $filters = $request->only(['search', 'question_category_id', 'question_type', 'status']);

        $questions = QuestionBank::query()
            ->with(['category:id,name', 'division:id,name'])
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('question', 'like', "%{$s}%"))
            ->when($filters['question_category_id'] ?? null, fn ($q, $v) => $q->where('question_category_id', $v))
            ->when($filters['question_type'] ?? null, fn ($q, $v) => $q->where('question_type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('qa.quiz.questions.index', [
            'questions' => $questions,
            'filters' => $filters,
            'categories' => QuestionCategory::ordered()->get(['id', 'name']),
            'canCreate' => $request->user()->can('question_bank.create'),
            'canUpdate' => $request->user()->can('question_bank.update'),
            'canDelete' => $request->user()->can('question_bank.delete'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QuestionBank::class);

        return view('qa.quiz.questions.create', $this->formData(new QuestionBank([
            'question_type' => 'single',
            'difficulty' => 'medium',
            'point' => 1,
            'status' => 'active',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', QuestionBank::class);

        $data = $this->validated($request);

        $question = DB::transaction(function () use ($data) {
            $question = QuestionBank::create($data['attributes'] + [
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncOptions($question, $data['options'], $data['correct']);

            return $question;
        });

        $this->audit->log('quiz.question.create', $question, [], $question->only(['question', 'question_type']));

        return redirect()
            ->route('qa.quiz.questions.index')
            ->with('success', 'Soal berhasil ditambahkan ke bank soal.');
    }

    public function edit(QuestionBank $question): View
    {
        $this->authorize('update', $question);

        return view('qa.quiz.questions.edit', $this->formData($question->load('options')));
    }

    public function update(Request $request, QuestionBank $question): RedirectResponse
    {
        $this->authorize('update', $question);

        $data = $this->validated($request);
        $old = $question->only(['question', 'question_type', 'point']);

        DB::transaction(function () use ($question, $data) {
            $question->update($data['attributes'] + ['updated_by' => auth()->id()]);
            $this->syncOptions($question, $data['options'], $data['correct']);
        });

        $this->audit->log('quiz.question.update', $question, $old, $question->fresh()->only(['question', 'question_type', 'point']));

        return redirect()
            ->route('qa.quiz.questions.index')
            ->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(QuestionBank $question): RedirectResponse
    {
        $this->authorize('delete', $question);

        // Soal yang sudah dipakai di sebuah versi quiz tidak dihapus keras:
        // versi memakai salinan, tetapi menghapus bank soal akan memutus jejak
        // asal soal. Nonaktifkan bila tidak dipakai lagi.
        if (QuizVersionQuestion::where('question_bank_id', $question->getKey())->exists()) {
            return redirect()
                ->route('qa.quiz.questions.index')
                ->with('error', 'Soal sudah dipakai pada quiz dan tidak dapat dihapus. Nonaktifkan bila tidak dipakai lagi.');
        }

        $this->audit->log('quiz.question.delete', null, $question->only(['question']), []);
        $question->delete();

        return redirect()
            ->route('qa.quiz.questions.index')
            ->with('success', 'Soal berhasil dihapus.');
    }

    /* ------------------------------------------------------------------ */
    /* Bantu                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{attributes: array<string, mixed>, options: list<array{label:?string, text:string}>, correct: list<int>}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'question_category_id' => ['required', 'integer', 'exists:question_categories,id'],
            'topic' => ['nullable', 'string', 'max:255'],
            'question' => ['required', 'string', 'max:2000'],
            'question_type' => ['required', Rule::in(QuestionBank::questionTypes())],
            'difficulty' => ['required', Rule::in(QuestionBank::difficulties())],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'point' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'options' => ['required', 'array', 'min:2'],
            'options.*.text' => ['required', 'string', 'max:1000'],
            'options.*.label' => ['nullable', 'string', 'max:8'],
            'correct' => ['required', 'array', 'min:1'],
            'correct.*' => ['integer'],
        ], [
            'options.min' => 'Setiap soal minimal memiliki dua pilihan jawaban.',
            'correct.min' => 'Tentukan minimal satu kunci jawaban yang benar.',
        ]);

        // Normalisasi: dropdown divisi "semua" mengirim string kosong.
        $departmentId = $validated['department_id'] ?? null;
        $validated['department_id'] = ($departmentId === '' || $departmentId === null) ? null : $departmentId;

        return [
            'attributes' => [
                'department_id' => $validated['department_id'],
                'question_category_id' => $validated['question_category_id'],
                'topic' => $validated['topic'] ?? null,
                'question' => $validated['question'],
                'question_type' => $validated['question_type'],
                'difficulty' => $validated['difficulty'],
                'explanation' => $validated['explanation'] ?? null,
                'point' => $validated['point'],
                'status' => $validated['status'],
            ],
            'options' => $validated['options'],
            'correct' => $validated['correct'],
        ];
    }

    /**
     * Tulis ulang opsi soal sesuai urutan form, lalu tandai kunci jawaban.
     *
     * Opsi lama dihapus & ditulis ulang: jumlah opsi bisa berkurang, sehingga
     * "update di tempat" berisiko menyisakan opsi lama yang tidak diinginkan.
     *
     * @param  list<array{label:?string, text:string}>  $options
     * @param  list<int|string>  $correctIndexes  indeks (berbasis 0) opsi yang benar
     */
    private function syncOptions(QuestionBank $question, array $options, array $correctIndexes): void
    {
        $question->options()->delete();

        $correctIndexes = array_map('intval', $correctIndexes);
        $defaultLabels = range('A', 'Z');

        foreach (array_values($options) as $index => $option) {
            $question->options()->create([
                'option_label' => $option['label'] ?? ($defaultLabels[$index] ?? null),
                'option_text' => $option['text'],
                'is_correct' => in_array($index, $correctIndexes, true),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(QuestionBank $question): array
    {
        return [
            'question' => $question,
            'categories' => QuestionCategory::active()->ordered()->get(['id', 'name']),
            'divisions' => Department::orderBy('name')->get(['id', 'name']),
        ];
    }
}
