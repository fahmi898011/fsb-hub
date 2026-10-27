<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pengelolaan Role & Permission (bagian dari Manajemen Pengguna).
 *
 * Halaman ini menggantikan cara lama (edit RoleAndPermissionSeeder lalu
 * jalankan seeder) supaya admin bisa mengatur hak akses sendiri.
 *
 * Role fungsional bawaan (7) sengaja TIDAK bisa dihapus — lihat RolePolicy.
 */
class RoleController extends Controller
{
    /**
     * Permission dikelompokkan agar form mudah dibaca.
     * Kunci = nama grup, nilai = daftar permission.
     *
     * @var array<string, list<string>>
     */
    private const PERMISSION_GROUPS = [
        'Informasi' => [
            'information.view',
            'information.create',
            'information.update',
            'information.publish',
            'information.archive',
            'information.version',
        ],
        'Kategori' => [
            'category.view',
            'category.create',
            'category.update',
            'category.delete',
        ],
        'Pengguna' => [
            'user.view',
            'user.create',
            'user.update',
            'user.disable',
            'user.import',
        ],
        'Tag' => [
            'tag.view',
            'tag.update',
            'tag.delete',
        ],
        'Divisi' => [
            'department.view',
            'department.create',
            'department.update',
            'department.delete',
        ],
        'Tim' => [
            'team.view',
            'team.create',
            'team.update',
            'team.delete',
        ],
    ];

    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', [
            'roles' => $roles,
            'functionalRoles' => User::functionalRoles(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', [
            'groups' => $this->permissionGroups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'name')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ], [
            'name.regex' => 'Nama role hanya boleh huruf kecil, angka, dan garis bawah.',
            'name.unique' => 'Nama role sudah dipakai.',
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        $this->forgetPermissionCache();
        $this->audit->log('role.create', null, [], [
            'role' => $role->name,
            'permissions' => $role->permissions->pluck('name')->all(),
        ]);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role {$role->name} berhasil dibuat.");
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'groups' => $this->permissionGroups(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $permissions = $data['permissions'] ?? [];

        // Anti-lockout: role admin wajib tetap memegang izin kunci.
        if (! app(RolePolicy::class)->keepsAdminAccess($role, $permissions)) {
            $required = implode(', ', RolePolicy::ADMIN_REQUIRED_PERMISSIONS);

            return back()->withErrors([
                'permissions' => "Role admin wajib mempertahankan permission: {$required}.",
            ]);
        }

        $before = $role->permissions->pluck('name')->all();

        $role->syncPermissions($permissions);

        $this->forgetPermissionCache();
        $this->audit->log('role.update', null, ['permissions' => $before], [
            'role' => $role->name,
            'permissions' => $role->permissions()->pluck('name')->all(),
        ]);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Permission role {$role->name} berhasil diperbarui.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $name = $role->name;
        $role->delete();

        $this->forgetPermissionCache();
        $this->audit->log('role.delete', null, ['role' => $name], []);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role {$name} berhasil dihapus.");
    }

    /* ------------------------------------------------------------------ */
    /* Internal                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Permission dari database, dikelompokkan agar mudah dibaca.
     * Permission yang belum masuk grup mana pun tetap ditampilkan
     * (di grup "Lain-lain"), supaya tidak ada izin yang "hilang" dari UI.
     *
     * @return array<string, \Illuminate\Support\Collection<int, Permission>>
     */
    private function permissionGroups(): array
    {
        $all = Permission::orderBy('name')->get();
        $grouped = [];
        $covered = [];

        foreach (self::PERMISSION_GROUPS as $label => $names) {
            $items = $all->whereIn('name', $names)->values();

            if ($items->isNotEmpty()) {
                $grouped[$label] = $items;
                $covered = array_merge($covered, $items->pluck('name')->all());
            }
        }

        $rest = $all->whereNotIn('name', $covered)->values();

        if ($rest->isNotEmpty()) {
            $grouped['Lain-lain'] = $rest;
        }

        return $grouped;
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
