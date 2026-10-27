<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Manajemen Pengguna — bagian dari Application Management (Admin).
 *
 * PRD §6/§16 — Admin mengatur *akses* aplikasi (akun, role, status aktif),
 * bukan konten. Permission yang dipakai: user.view / user.create /
 * user.update / user.disable.
 */
class UserController extends Controller
{
    /** Role yang boleh dikelola lewat UI ini. */
    private const MANAGEABLE_ROLES = [
        'admin',
        'qa',
        'employee',
        'supervisor',
        'project_leader',
        'it',
        'hr',
    ];

    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['search', 'role', 'status']);

        $users = User::query()
            ->with(['roles', 'division'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        // Cari juga di master divisi, karena sejak ada tabel
                        // `departments` kolom teks di atas hanya cadangan.
                        ->orWhereHas('division', fn ($d) => $d->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['role'] ?? null, function ($query, $role) {
                // `none` = akun tanpa peran sama sekali. Ini temuan impor yang
                // paling sering, dan dashboard Admin menautkannya langsung.
                // AGENT: asisten | 2026-10-20 | dukung filter tanpa peran | modul Manajemen Pengguna
                return $role === 'none'
                    ? $query->doesntHave('roles')
                    : $query->role($role);
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => $this->roles(),
            'stats' => $this->stats(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'user' => new User(['status' => 'active']),
            'roles' => $this->roles(),
            'departments' => Department::ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $this->validated($request);
        $role = $data['role'];
        unset($data['role']);

        $data['password'] = Hash::make($data['password']);
        $data['status'] ??= 'active';

        $user = DB::transaction(function () use ($data, $role) {
            $user = User::create($data);
            $user->syncRoles([$role]);

            return $user;
        });

        $this->audit->log('user.created', $user, [], [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'status' => $user->status,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Pengguna {$user->name} berhasil ditambahkan.");
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load('roles.permissions');

        return view('admin.users.show', ['user' => $user]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->roles(),
            'departments' => Department::ordered()->get(),
        ]);
    }



    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $this->validated($request, $user);
        $role = $data['role'];
        unset($data['role']);

        // Password hanya diubah bila diisi.
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        // Cegah admin menurunkan role akunnya sendiri (lockout).
        if ($user->is(Auth::user()) && $role !== 'admin') {
            return back()->withInput()->with('error', 'Anda tidak dapat mengubah role akun Anda sendiri.');
        }

        $old = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'status' => $user->status,
        ];

        DB::transaction(function () use ($user, $data, $role) {
            $user->update($data);
            $user->syncRoles([$role]);
        });

        $this->audit->log('user.updated', $user, $old, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'status' => $user->status,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Pengguna {$user->name} berhasil diperbarui.");
    }

    /**
     * Aktif / nonaktifkan akun (soft disable).
     *
     * PRD §6 — Admin menonaktifkan akses, bukan menghapus data historis.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        $this->authorize('disable', $user);

        $previous = $user->status;
        $newStatus = $previous === 'active' ? 'inactive' : 'active';

        $user->update(['status' => $newStatus]);

        $this->audit->log('user.status_changed', $user, ['status' => $previous], ['status' => $newStatus]);

        $label = $newStatus === 'active' ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Akun {$user->name} berhasil {$label}.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $name = $user->name;

        $this->audit->log('user.deleted', $user, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
        ], []);

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Pengguna {$name} berhasil dihapus.");
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($user?->getKey()),
            ],
            'employee_id' => [
                'nullable', 'string', 'max:50',
                Rule::unique('users', 'employee_id')->ignore($user?->getKey()),
            ],
            'department' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(self::MANAGEABLE_ROLES)],
            'status' => ['required', 'in:active,inactive'],
        ];

        // Password wajib saat membuat; opsional (kosong = tidak diubah) saat update.
        $rules['password'] = $user
            ? ['nullable', 'string', 'min:8', 'confirmed']
            : ['required', 'string', 'min:8', 'confirmed'];

        return $request->validate($rules);
    }

    /**
     * Daftar role untuk dropdown.
     *
     * @return \Illuminate\Support\Collection<int, Role>
     */
    private function roles()
    {
        return Role::whereIn('name', self::MANAGEABLE_ROLES)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        return [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'inactive' => User::where('status', 'inactive')->count(),
            'qa' => User::role('qa')->count(),
        ];
    }
}

