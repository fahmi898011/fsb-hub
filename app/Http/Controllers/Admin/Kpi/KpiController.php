<?php

namespace App\Http\Controllers\Admin\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Kpi;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master KPI (Admin) - PRD Employee KPI/QA §4.
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 */
class KpiController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Kpi::class);

        $filters = $request->only(['search', 'status', 'department']);

        $kpis = Kpi::query()
            ->with('division')
            ->withCount('scorecards')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['department'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        $divisions = Department::ordered()->get();

        return view('admin.kpi.kpis.index', compact('kpis', 'filters', 'divisions'));
    }

    public function create(): View
    {
        $this->authorize('create', Kpi::class);

        return view('admin.kpi.kpis.create', [
            'kpi' => new Kpi(),
            'divisions' => Department::ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Kpi::class);

        $data = $this->validated($request);

        $kpi = Kpi::create($data + ['slug' => $this->uniqueSlug($data['name'])]);

        $this->audit->log('kpi.kpi.create', $kpi, [], [
            'name' => $kpi->name,
            'status' => $kpi->status,
        ]);

        return redirect()
            ->route('admin.kpi.kpis.index')
            ->with('success', "KPI {$kpi->name} berhasil ditambahkan.");
    }

    public function edit(Kpi $kpi): View
    {
        $this->authorize('update', $kpi);

        $kpi->loadCount('scorecards');

        return view('admin.kpi.kpis.edit', [
            'kpi' => $kpi,
            'divisions' => Department::ordered()->get(),
        ]);
    }

    public function update(Request $request, Kpi $kpi): RedirectResponse
    {
        $this->authorize('update', $kpi);

        $data = $this->validated($request, $kpi);
        $before = ['name' => $kpi->name, 'status' => $kpi->status];

        $kpi->update($data + ['slug' => $this->uniqueSlug($data['name'], $kpi)]);

        $this->audit->log('kpi.kpi.update', $kpi, $before, [
            'name' => $kpi->name,
            'status' => $kpi->status,
        ]);

        return redirect()
            ->route('admin.kpi.kpis.index')
            ->with('success', "KPI {$kpi->name} berhasil diperbarui.");
    }

    public function destroy(Kpi $kpi): RedirectResponse
    {
        $this->authorize('delete', $kpi);

        $name = $kpi->name;

        $kpi->delete();

        $this->audit->log('kpi.kpi.delete', null, ['name' => $name], []);

        return redirect()
            ->route('admin.kpi.kpis.index')
            ->with('success', "KPI {$name} berhasil dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Kpi $ignore = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('kpis', 'name')->ignore($ignore?->getKey()),
            ],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'name.unique' => 'Nama KPI sudah dipakai.',
        ]);

        $data['sort_order'] ??= 0;

        return $data;
    }

    private function uniqueSlug(string $name, ?Kpi $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Kpi::where('slug', $slug)->whereKeyNot($ignore?->getKey() ?? 0)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
