<?php

namespace App\Http\Controllers\Qa\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\QuestionCategory;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Str;

/**
 * Kategori bank soal (PRD Employee KPI/QA §21) — dikelola QA.
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Kategori dipakai untuk menata soal dan untuk pemilihan soal berbasis kategori
 * pada versi quiz (PRD §24). Menghapus kategori tidak menghapus soal: kolom
 * `question_banks.question_category_id` memakai `nullOnDelete` (PRD §11).
 */
class QuestionCategoryController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        $this->authorize('viewAny', QuestionCategory::class);

        $categories = QuestionCategory::query()
            ->with('division:id,name')
            ->withCount('questions')
            ->ordered()
            ->paginate(20);

        return view('qa.quiz.categories.index', [
            'categories' => $categories,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QuestionCategory::class);

        return view('qa.quiz.categories.create', [
            'category' => new QuestionCategory(['status' => 'active', 'sort_order' => 0]),
            'divisions' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', QuestionCategory::class);

        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $category = QuestionCategory::create($data + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]);

        $this->audit->log('quiz.category.create', $category, [], $category->only(['name']));

        return redirect()->route('qa.quiz.categories.index')->with('success', 'Kategori soal berhasil ditambahkan.');
    }

    public function edit(QuestionCategory $category): View
    {
        $this->authorize('update', $category);

        return view('qa.quiz.categories.edit', [
            'category' => $category,
            'divisions' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, QuestionCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $data = $this->validated($request);
        if ($category->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        }
        $category->update($data + ['updated_by' => auth()->id()]);

        $this->audit->log('quiz.category.update', $category, [], $category->only(['name']));

        return redirect()->route('qa.quiz.categories.index')->with('success', 'Kategori soal berhasil diperbarui.');
    }

    public function destroy(QuestionCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        // Soal tidak dihapus, hanya kehilangan kategorinya (nullOnDelete).
        $category->delete();

        $this->audit->log('quiz.category.delete', null, $category->only(['name']), []);

        return redirect()->route('qa.quiz.categories.index')->with('success', 'Kategori soal berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $data['department_id'] = ($data['department_id'] ?? null) === '' ? null : ($data['department_id'] ?? null);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $suffix = 2;

        while (QuestionCategory::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
