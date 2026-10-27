<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Team;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master Divisi (Admin).
 *
 * Mengubah `users.department` dari teks bebas menjadi master data, supaya
 * "IT", "it", dan "I.T." tidak lagi dianggap tiga divisi berbeda — masalah
 * yang membuat rekap per divisi (mis. KPI) tidak bisa diandalkan.
 *
 * Master ini mengatur STRUKTUR, bukan hak akses. Hak akses tetap lewat Role
 * (halaman `admin/peran/*`).
 */
class DepartmentController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Department::class);

        $filters = $request->only(['search', 'status']);

        $departments = Department::query()
            ->withCount(['users', 'teams'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('admin.departments.index', compact('departments', 'filters'));
    }

    public function create(): View
    {
        $this->authorize('create', Department::class);

        // Model kosong dikirim agar partial `_form` (dipakai bersama halaman
        // tambah & edit) bisa membaca `$department->exists` tanpa error —
        // pola yang sama seperti CategoryController::create().
        return view('admin.departments.create', ['department' => new Department()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Department::class);

        $data = $this->validated($request);

        $department = Department::create($data + [
            'slug' => $this->uniqueSlug($data['name']),
        ]);

        $this->audit->log('department.create', $department, [], [
            'name' => $department->name,
            'status' => $department->status,
        ]);

        return redirect()
            ->route('admin.departments.index')
            ->with('success', "Divisi {$department->name} berhasil ditambahkan.");
    }

    public function edit(Department $department): View
    {
        $this->authorize('update', $department);

        $department->loadCount(['users', 'teams']);

        return view('admin.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $data = $this->validated($request, $department);
        $before = ['name' => $department->name, 'status' => $department->status];

        $department->update($data + [
            'slug' => $this->uniqueSlug($data['name'], $department),
        ]);

        $this->audit->log('department.update', $department, $before, [
            'name' => $department->name,
            'status' => $department->status,
        ]);

        return redirect()
            ->route('admin.departments.index')
            ->with('success', "Divisi {$department->name} berhasil diperbarui.");
    }

    public function destroy(Department $department): RedirectResponse
    {
        // Policy menolak penghapusan bila divisi masih dipakai user/tim.
        $this->authorize('delete', $department);

        $name = $department->name;

        $department->delete();

        $this->audit->log('department.delete', null, ['name' => $name], []);

        return redirect()
            ->route('admin.departments.index')
            ->with('success', "Divisi {$name} berhasil dihapus.");
    }

    /* ------------------------------------------------------------------ */
    /* Internal                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Department $ignore = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('departments', 'name')->ignore($ignore?->getKey()),
            ],
            'code' => [
                'nullable', 'string', 'max:10',
                Rule::unique('departments', 'code')->ignore($ignore?->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'name.unique' => 'Nama divisi sudah dipakai.',
            'code.unique' => 'Kode divisi sudah dipakai.',
        ]);

        // Kolom `sort_order` NOT NULL, sedangkan inputnya opsional: kosong
        // harus menjadi 0, bukan null. (`??=` dipakai karena operator `+`
        // tidak menimpa nilai null.)
        $data['sort_order'] ??= 0;

        return $data;
    }

    private function uniqueSlug(string $name, ?Department $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Department::where('slug', $slug)->whereKeyNot($ignore?->getKey() ?? 0)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
