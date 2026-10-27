<?php

namespace App\Http\Controllers\Qa\Quiz;

use App\Http\Controllers\Controller;
use App\Models\QuestionBank;
use App\Models\QuizTemplate;
use App\Models\QuizVersion;
use App\Services\AuditLogService;
use App\Services\QuizService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuizVersionController extends Controller
{
    public function __construct(
        private readonly QuizService $quizzes,
        private readonly AuditLogService $audit,
    ) {}

    public function create(QuizTemplate $template): View
    {
        $this->authorize('create', QuizVersion::class);

        $questionIds = $this->quizzes->selectQuestionIds($template);

        return view('qa.quiz.versions.form', [
            'template' => $template,
            'version' => new QuizVersion([
                'passing_score' => $template->passing_score,
                'time_limit' => $template->time_limit,
            ]),
            'questions' => $this->availableQuestions($template),
            'selectedIds' => $questionIds,
            'isNew' => true,
        ]);
    }

    public function store(Request $request, QuizTemplate $template): RedirectResponse
    {
        $this->authorize('create', QuizVersion::class);
        $data = $this->validated($request, $template);
        $version = $this->quizzes->createVersion($template, $data, $request->user());
        $version->update(['updated_by' => $request->user()->id]);

        $this->audit->log('quiz.version.create', $version, [], ['version' => $version->version]);

        return redirect()->route('qa.quiz.versions.show', $version)->with('success', 'Versi quiz berhasil dibuat.');
    }

    public function show(QuizVersion $version): View
    {
        $this->authorize('view', $version);
        $version->load(['template.division:id,name', 'questions.question.category:id,name']);

        return view('qa.quiz.versions.show', compact('version'));
    }

    public function edit(QuizVersion $version): View
    {
        $this->authorize('update', $version);
        $version->load('template', 'questions');

        return view('qa.quiz.versions.form', [
            'template' => $version->template,
            'version' => $version,
            'questions' => $this->availableQuestions($version->template),
            'selectedIds' => $version->questions->pluck('question_bank_id')->filter()->all(),
            'isNew' => false,
        ]);
    }

    public function update(Request $request, QuizVersion $version): RedirectResponse
    {
        $this->authorize('update', $version);
        $data = $this->validated($request, $version->template);

        DB::transaction(function () use ($version, $data, $request) {
            $version->update([
                'passing_score' => $data['passing_score'],
                'time_limit' => $data['time_limit'],
                'effective_from' => $data['effective_from'],
                'notes' => $data['notes'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
            $this->quizzes->syncQuestions($version, $data['question_ids']);
        });

        $this->audit->log('quiz.version.update', $version, [], ['question_count' => count($data['question_ids'])]);

        return redirect()->route('qa.quiz.versions.show', $version)->with('success', 'Versi quiz berhasil diperbarui.');
    }

    public function publish(QuizVersion $version): RedirectResponse
    {
        $this->authorize('publish', $version);
        $published = $this->quizzes->publish($version);
        $this->audit->log('quiz.version.publish', $published, [], ['status' => 'active']);

        return redirect()->route('qa.quiz.versions.show', $published)->with('success', 'Versi quiz berhasil diterbitkan.');
    }

    private function validated(Request $request, QuizTemplate $template): array
    {
        $data = $request->validate([
            'question_ids' => ['required', 'array', 'min:1', 'max:200'],
            'question_ids.*' => ['required', 'integer', 'distinct', 'exists:question_banks,id'],
            'passing_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'time_limit' => ['nullable', 'integer', 'min:1', 'max:600'],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $allowedIds = $this->availableQuestions($template)->modelKeys();
        abort_if(array_diff(array_map('intval', $data['question_ids']), $allowedIds), 422, 'Daftar soal berisi soal nonaktif atau di luar divisi template.');
        if (count($data['question_ids']) !== (int) $template->question_count) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'question_ids' => "Pilih tepat {$template->question_count} soal sesuai ketentuan template.",
            ]);
        }

        return $data;
    }

    private function availableQuestions(QuizTemplate $template)
    {
        return QuestionBank::query()
            ->active()
            ->with(['category:id,name', 'options'])
            ->when($template->department_id, fn ($q, $id) => $q->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $id)))
            ->orderBy('question_category_id')->orderBy('id')->get();
    }
}