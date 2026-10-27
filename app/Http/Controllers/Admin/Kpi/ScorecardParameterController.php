<?php

namespace App\Http\Controllers\Admin\Kpi;

use App\Http\Controllers\Controller;
use App\Models\ScorecardCategory;
use App\Models\ScorecardParameter;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Parameter versi scorecard (Admin) - PRD Employee KPI/QA §8/§13.
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Di sinilah BOBOT dan tipe parameter diatur - sepenuhnya wewenang Admin.
 * QA tidak dapat menyentuh halaman ini (lihat ScorecardParameterPolicy). Sama
 * seperti kategori, parameter hanya boleh disunting selama versinya DRAFT.
 *
 * Catatan validasi penting: ketika `parameter_type = zero_tolerance` dengan
 * `ztp_effect = zero_score`, bobot dipaksa 0 karena baris ZTP yang memaksa skor
 * menjadi 0 TIDAK boleh ikut menambah total 100%. Sebaliknya ZTP `weight_only`
 * tetap boleh berbobot karena ia memang berfungsi sebagai penalty biasa.
 */
class ScorecardParameterController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function create(ScorecardCategory $category): View
    {
        $this->authorize('view', $category);

        return view('admin.kpi.parameters.create', [
            'category' => $category,
            'parameter' => new ScorecardParameter(),
        ]);
    }

    public function store(Request $request, ScorecardCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        if ($locked = $this->lockedResponse($category)) {
            return $locked;
        }

        $data = $this->validated($request, $category);

        $parameter = $category->parameters()->create($data);

        $this->audit->log('kpi.parameter.create', $parameter, [], [
            'name' => $parameter->name,
            'weight' => $parameter->weight,
            'parameter_type' => $parameter->parameter_type,
        ]);

        return redirect()
            ->route('admin.kpi.versions.show', $category->version)
            ->with('success', "Parameter {$parameter->name} berhasil ditambahkan.");
    }

    public function edit(ScorecardParameter $parameter): View
    {
        $this->authorize('view', $parameter);

        return view('admin.kpi.parameters.edit', compact('parameter'));
    }

    public function update(Request $request, ScorecardParameter $parameter): RedirectResponse
    {
        $this->authorize('update', $parameter);

        $category = $parameter->category;

        if ($locked = $this->lockedResponse($category)) {
            return $locked;
        }

        $data = $this->validated($request, $category, $parameter);

        // Rekam PERUBAHAN BOBOT secara eksplisit di audit log - inilah inti
        // PRD §36 (contoh: Empati 6% -> 8%).
        $before = [
            'name' => $parameter->name,
            'weight' => (float) $parameter->weight,
            'parameter_type' => $parameter->parameter_type,
        ];

        $parameter->update($data);

        $this->audit->log('kpi.parameter.update', $parameter, $before, [
            'name' => $parameter->name,
            'weight' => (float) $parameter->weight,
            'parameter_type' => $parameter->parameter_type,
        ]);

        return redirect()
            ->route('admin.kpi.versions.show', $category->version)
            ->with('success', "Parameter {$parameter->name} berhasil diperbarui.");
    }

    public function destroy(ScorecardParameter $parameter): RedirectResponse
    {
        $this->authorize('delete', $parameter);

        $category = $parameter->category;

        if ($locked = $this->lockedResponse($category)) {
            return $locked;
        }

        $name = $parameter->name;

        $parameter->delete();

        $this->audit->log('kpi.parameter.delete', null, ['name' => $name], []);

        return redirect()
            ->route('admin.kpi.versions.show', $category->version)
            ->with('success', "Parameter {$name} berhasil dihapus.");
    }

    private function lockedResponse(ScorecardCategory $category): ?RedirectResponse
    {
        if ($category->version->status !== 'draft') {
            return back()->with('error', 'Versi yang sudah aktif/tidak lagi draft tidak boleh diubah. Buat versi baru untuk mengubah bobot.');
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ScorecardCategory $category, ?ScorecardParameter $ignore = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('scorecard_parameters', 'name')
                    ->where('scorecard_category_id', $category->getKey())
                    ->ignore($ignore?->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'evaluation_type' => ['required', Rule::in(ScorecardParameter::evaluationTypes())],
            'parameter_type' => ['required', Rule::in(ScorecardParameter::parameterTypes())],
            'ztp_effect' => ['required', Rule::in(ScorecardParameter::ztpEffects())],
            'answer_polarity' => ['required', Rule::in(['normal', 'inverted'])],
            'guideline' => ['nullable', 'string', 'max:5000'],
            'penalty_rule' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'name.unique' => 'Nama parameter sudah dipakai pada kategori ini.',
            'weight.required' => 'Bobot wajib diisi (dalam persen).',
        ]);

        // Normalisasi tipe: ZTP zero_score tanpa bobot, dan hanya ZTP yang
        // memakai efek/polaritas khusus. Parameter biasa dipaksa 'normal'
        // supaya tidak ada nilai sisa yang membingungkan.
        $isZeroTolerance = $data['parameter_type'] === 'zero_tolerance';

        if (! $isZeroTolerance) {
            $data['ztp_effect'] = 'zero_score';
            $data['answer_polarity'] = 'normal';
        } elseif ($data['ztp_effect'] === 'zero_score') {
            $data['weight'] = 0;
        }

        $data['is_required'] = $request->boolean('is_required', true);
        $data['sort_order'] ??= 0;

        return $data;
    }
}
