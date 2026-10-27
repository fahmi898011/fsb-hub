<?php

namespace App\Http\Controllers\Qa\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\QuizTemplate;
use App\Services\AuditLogService;
use App\Services\QuizService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Str;

/**
 * Template quiz (PRD Employee KPI/QA §22/§33) — dikelola QA.
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Template adalah "jenis quiz". Versi (daftar soal & aturan berlaku) dikelola
 * di QuizVersionController. Menghapus template menghapus versinya (cascade),
 * sehingga dijaga: template yang sudah punya attempt tidak dapat dihapus.
 */
class QuizTemplateController extends Controller
{
    public function __construct(
        private readonly QuizService $quizzes,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', QuizTemplate::class);

        $filters = $request->only(['search', 'status']);

        $templates = QuizTemplate::query()
            ->with('division:id,name')
            ->withCount('versions')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('qa.quiz.templates.index', [
            'templates' => $templates,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QuizTemplate::class);

        return view('qa.quiz.templates.create', $this->formData(new QuizTemplate([
            'question_count' => 10,
            'passing_score' => 80,
            'time_limit' => 30,
            'selection_method' => 'manual',
            'status' => 'draft',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', QuizTemplate::class);

        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $template = QuizTemplate::create($data + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->audit->log('quiz.template.create', $template, [], $template->only(['name', 'question_count']));

        return redirect()->route('qa.quiz.templates.show', $template)->with('success', 'Template quiz berhasil dibuat.');
    }

    public function show(QuizTemplate $template): View
    {
        $this->authorize('view', $template);

        $template->load(['division:id,name', 'versions' => fn ($q) => $q->orderByDesc('version')]);

        return view('qa.quiz.templates.show', [
            'template' => $template,
        ]);
    }

    public function edit(QuizTemplate $template): View
    {
        $this->authorize('update', $template);

        return view('qa.quiz.templates.edit', $this->formData($template));
    }

    public function update(Request $request, QuizTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);

        $data = $this->validated($request);
        if ($template->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $template->id);
        }
        $template->update($data + ['updated_by' => auth()->id()]);

        $this->audit->log('quiz.template.update', $template, [], $template->only(['name', 'question_count']));

        return redirect()->route('qa.quiz.templates.show', $template)->with('success', 'Template quiz berhasil diperbarui.');
    }

    public function destroy(QuizTemplate $template): RedirectResponse
    {
        $this->authorize('delete', $template);

        // Template yang sudah dikerjakan tidak dihapus: hasil attempt harus
        // tetap dapat ditelusuri (PRD §11).
        if ($template->attempts()->exists()) {
            return redirect()->route('qa.quiz.templates.index')
                ->with('error', 'Template sudah dikerjakan karyawan dan tidak dapat dihapus. Nonaktifkan bila tidak dipakai lagi.');
        }

        $this->audit->log('quiz.template.delete', null, $template->only(['name']), []);
        $template->delete();

        return redirect()->route('qa.quiz.templates.index')->with('success', 'Template quiz berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'question_count' => ['required', 'integer', 'min:1', 'max:200'],
            'passing_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'time_limit' => ['nullable', 'integer', 'min:1', 'max:600'],
            'selection_method' => ['required', Rule::in(QuizTemplate::selectionMethods())],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive'])],
        ]);

        $data['department_id'] = ($data['department_id'] ?? null) === '' ? null : ($data['department_id'] ?? null);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(QuizTemplate $template): array
    {
        return [
            'template' => $template,
            'divisions' => Department::orderBy('name')->get(['id', 'name']),
        ];
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'quiz';
        $slug = $base;
        $suffix = 2;

        while (QuizTemplate::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
