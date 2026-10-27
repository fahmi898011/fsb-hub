<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Models\QaAssessmentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Controller kategori assessment QA (PRD QA Assessment §3).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A
 * AGENT: asisten | 2026-10-20 | isi slug unik saat simpan | modul QA Assessment, kategori
 */
class QaAssessmentCategoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', QaAssessmentCategory::class);

        $categories = QaAssessmentCategory::query()
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('qa.assessment.categories.index', compact('categories'));
    }

    public function create()
    {
        $this->authorize('create', QaAssessmentCategory::class);

        return view('qa.assessment.categories.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', QaAssessmentCategory::class);

        $data = $request->validate([
            'name' => 'required|string|max:150|unique:qa_assessment_categories,name',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['status'] = $data['status'] ?? 'active';
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        // `slug` NOT NULL & unik di DB — wajib diisi di sini.
        $data['slug'] = $this->uniqueSlug($data['name']);

        $category = QaAssessmentCategory::create($data);

        return redirect()
            ->route('qa.assessment.categories.index')
            ->with('success', 'Kategori assessment berhasil dibuat.');
    }

    public function show(QaAssessmentCategory $qaAssessmentCategory)
    {
        $this->authorize('view', $qaAssessmentCategory);

        $qaAssessmentCategory->loadCount(['assessments', 'assessments as published_assessments_count' => fn ($q) => $q->where('status', 'published')]);

        return view('qa.assessment.categories.show', compact('qaAssessmentCategory'));
    }

    public function edit(QaAssessmentCategory $qaAssessmentCategory)
    {
        $this->authorize('update', $qaAssessmentCategory);

        return view('qa.assessment.categories.edit', compact('qaAssessmentCategory'));
    }

    public function update(Request $request, QaAssessmentCategory $qaAssessmentCategory)
    {
        $this->authorize('update', $qaAssessmentCategory);

        $data = $request->validate([
            'name' => 'required|string|max:150|unique:qa_assessment_categories,name,' . $qaAssessmentCategory->id,
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['status'] = $data['status'] ?? 'active';
        $data['updated_by'] = auth()->id();

        // Nama berubah → slug ikut menyesuaikan agar tidak ada dua kategori
        // dengan slug usang yang sama.
        if ($qaAssessmentCategory->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $qaAssessmentCategory);
        }

        $qaAssessmentCategory->update($data);

        return redirect()
            ->route('qa.assessment.categories.index')
            ->with('success', 'Kategori assessment berhasil diperbarui.');
    }

    public function destroy(QaAssessmentCategory $qaAssessmentCategory)
    {
        $this->authorize('delete', $qaAssessmentCategory);

        $qaAssessmentCategory->delete();

        return redirect()
            ->route('qa.assessment.categories.index')
            ->with('success', 'Kategori assessment berhasil dihapus.');
    }

    /**
     * Slug unik dari nama. Basis kosong (mis. nama hanya berisi simbol) jatuh
     * ke `kategori` agar kolom NOT NULL tidak pernah menerima string kosong.
     *
     * AGENT: asisten | 2026-10-20 | tambah helper slug | modul QA Assessment, kategori
     */
    private function uniqueSlug(string $name, ?QaAssessmentCategory $ignore = null): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $i = 2;

        while (QaAssessmentCategory::where('slug', $slug)
            ->whereKeyNot($ignore?->getKey() ?? 0)
            ->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
