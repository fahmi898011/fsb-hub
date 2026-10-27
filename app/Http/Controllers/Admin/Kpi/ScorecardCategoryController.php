<?php

namespace App\Http\Controllers\Admin\Kpi;

use App\Http\Controllers\Controller;
use App\Models\ScorecardCategory;
use App\Models\ScorecardVersion;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Kategori versi scorecard (Admin) - PRD Employee KPI/QA §7.
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Kategori hanya pengelompok. Yang dijaga di sini: kategori hanya boleh
 * disunting selama versinya masih DRAFT; begitu versi diaktifkan, isinya
 * dibekukan agar skor historis tidak bergeser (PRD §6).
 */
class ScorecardCategoryController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function create(ScorecardVersion $version): View
    {
        $this->authorize('view', $version);

        return view('admin.kpi.categories.create', [
            'version' => $version,
            'category' => new ScorecardCategory(),
        ]);
    }

    public function store(Request $request, ScorecardVersion $version): RedirectResponse
    {
        $this->authorize('update', $version);

        if ($locked = $this->lockedResponse($version)) {
            return $locked;
        }

        $data = $this->validated($request, $version);

        $category = $version->categories()->create($data);

        $this->audit->log('kpi.category.create', $category, [], [
            'name' => $category->name,
            'version' => $version->version,
        ]);

        return redirect()
            ->route('admin.kpi.versions.show', $version)
            ->with('success', "Kategori {$category->name} berhasil ditambahkan.");
    }

    public function edit(ScorecardCategory $category): View
    {
        $this->authorize('view', $category);

        return view('admin.kpi.categories.edit', compact('category'));
    }

    public function update(Request $request, ScorecardCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $version = $category->version;

        if ($locked = $this->lockedResponse($version)) {
            return $locked;
        }

        $data = $this->validated($request, $version, $category);
        $before = ['name' => $category->name, 'status' => $category->status];

        $category->update($data);

        $this->audit->log('kpi.category.update', $category, $before, [
            'name' => $category->name,
            'status' => $category->status,
        ]);

        return redirect()
            ->route('admin.kpi.versions.show', $version)
            ->with('success', "Kategori {$category->name} berhasil diperbarui.");
    }

    public function destroy(ScorecardCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $version = $category->version;

        if ($locked = $this->lockedResponse($version)) {
            return $locked;
        }

        $name = $category->name;

        $category->delete();

        $this->audit->log('kpi.category.delete', null, ['name' => $name, 'version' => $version->version], []);

        return redirect()
            ->route('admin.kpi.versions.show', $version)
            ->with('success', "Kategori {$name} berhasil dihapus.");
    }

    /**
     * Versi yang tidak lagi draft tidak boleh diubah isinya. Mengembalikan
     * respons redirect bila terkunci, atau null bila masih boleh disunting.
     */
    private function lockedResponse(ScorecardVersion $version): ?RedirectResponse
    {
        if ($version->status !== 'draft') {
            return back()->with('error', 'Versi yang sudah aktif/tidak lagi draft tidak boleh diubah. Buat versi baru untuk mengubah bobot.');
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ScorecardVersion $version, ?ScorecardCategory $ignore = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('scorecard_categories', 'name')
                    ->where('scorecard_version_id', $version->getKey())
                    ->ignore($ignore?->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'name.unique' => 'Nama kategori sudah dipakai pada versi ini.',
        ]);

        $data['sort_order'] ??= 0;

        return $data;
    }
}
