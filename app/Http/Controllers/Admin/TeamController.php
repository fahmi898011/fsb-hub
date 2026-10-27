<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Master Tim (Admin).
 *
 * Tim adalah unit kerja di bawah satu divisi dengan seorang leader. Tujuannya
 * agar supervisor / project leader punya dasar untuk melihat laporan timnya,
 * dan modul KPI (PRD KPI §11) punya struktur penilaian yang jelas.
 *
 * Keanggotaan disimpan di `team_members`. Satu user hanya boleh berada di satu
 * tim supaya rekap tim tidak ambigu — batasan ini ditegakkan di level skema
 * (unique pada `user_id`) dan divalidasi di sini agar pesan errornya ramah.
 */
class TeamController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Team::class);

        $filters = $request->only(['search', 'department', 'status']);

        // Supervisor / project leader hanya melihat timnya sendiri (yang
        // dipimpin atau yang diikutinya). Pemegang `team.update` (Admin)
        // melihat semua tim.
        $canManage = $request->user()->can('team.update');
        $userId = $request->user()->getKey();

        $teams = Team::query()
            ->with(['department', 'leader'])
            ->withCount('memberships')
            ->when(! $canManage, function ($query) use ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->where('leader_id', $userId)
                        ->orWhereHas('memberships', fn ($m) => $m->where('user_id', $userId));
                });
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['department'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $departments = Department::ordered()->get();

        return view('admin.teams.index', compact('teams', 'departments', 'filters'));
    }

    public function create(): View
    {
        $this->authorize('create', Team::class);

        // Model kosong dikirim agar partial `_form` (dipakai bersama halaman
        // tambah & edit) bisa membaca `$team->exists` tanpa error — pola yang
        // sama seperti CategoryController::create().
        return view('admin.teams.create', ['team' => new Team()] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Team::class);

        $data = $this->validated($request);

        // Dibungkus transaksi: bila penyusunan anggota gagal (mis. ada user
        // yang sudah masuk tim lain), pembuatan timnya ikut dibatalkan agar
        // tidak tertinggal tim kosong.
        $team = DB::transaction(function () use ($request, $data) {
            $team = Team::create(
                collect($data)->except('members')->all() + ['slug' => $this->uniqueSlug($data['name'])]
            );

            $this->syncMembers($team, $request);

            return $team;
        });

        $this->audit->log('team.create', $team, [], [
            'name' => $team->name,
            'department_id' => $team->department_id,
            'leader_id' => $team->leader_id,
        ]);

        return redirect()
            ->route('admin.teams.index')
            ->with('success', "Tim {$team->name} berhasil ditambahkan.");
    }

    public function edit(Team $team): View
    {
        $this->authorize('update', $team);

        $team->load(['members', 'department', 'leader']);

        return view('admin.teams.edit', ['team' => $team] + $this->formData($team));
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $data = $this->validated($request);
        $before = [
            'name' => $team->name,
            'department_id' => $team->department_id,
            'leader_id' => $team->leader_id,
        ];

        // Alasan sama seperti store(): jangan tinggalkan perubahan separuh.
        DB::transaction(function () use ($request, $data, $team) {
            $team->update(
                collect($data)->except('members')->all() + ['slug' => $this->uniqueSlug($data['name'], $team)]
            );

            $this->syncMembers($team, $request);
        });

        $this->audit->log('team.update', $team, $before, [
            'name' => $team->name,
            'department_id' => $team->department_id,
            'leader_id' => $team->leader_id,
        ]);

        return redirect()
            ->route('admin.teams.index')
            ->with('success', "Tim {$team->name} berhasil diperbarui.");
    }

    public function destroy(Team $team): RedirectResponse
    {
        // Policy menolak bila tim masih berisi anggota.
        $this->authorize('delete', $team);

        $name = $team->name;

        $team->delete();

        $this->audit->log('team.delete', null, ['name' => $name], []);

        return redirect()
            ->route('admin.teams.index')
            ->with('success', "Tim {$name} berhasil dihapus.");
    }

    /* ------------------------------------------------------------------ */
    /* Internal                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'exists:users,id'],
        ], [
            'members.*.exists' => 'Ada anggota yang tidak ditemukan.',
        ]);
    }

    /**
     * Ganti daftar anggota tim.
     *
     * `team_members.user_id` unik, jadi user yang sudah tergabung di tim lain
     * akan ditolak skema. Kita tolak lebih awal supaya pesannya jelas.
     */
    private function syncMembers(Team $team, Request $request): void
    {
        $memberIds = collect($request->input('members', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($memberIds->isNotEmpty()) {
            $taken = TeamMember::query()
                ->whereIn('user_id', $memberIds)
                ->where('team_id', '!=', $team->getKey())
                ->with('user')
                ->get();

            if ($taken->isNotEmpty()) {
                $names = $taken->pluck('user.name')->filter()->implode(', ');

                // ValidationException (bukan abort 422) supaya pengguna
                // dikembalikan ke form dengan pesan yang bisa dibaca, bukan
                // halaman error.
                throw ValidationException::withMessages([
                    'members' => "Karyawan berikut sudah tergabung di tim lain: {$names}.",
                ]);
            }
        }

        $team->memberships()->whereNotIn('user_id', $memberIds)->delete();

        foreach ($memberIds as $userId) {
            $team->memberships()->updateOrCreate(
                ['user_id' => $userId],
                ['role' => $userId === (int) $team->leader_id ? 'leader' : 'member'],
            );
        }

        // Leader yang dipilih otomatis menjadi anggota timnya sendiri.
        if ($team->leader_id && ! $memberIds->contains((int) $team->leader_id)) {
            $exists = TeamMember::where('user_id', $team->leader_id)->exists();

            if (! $exists) {
                $team->memberships()->create([
                    'user_id' => $team->leader_id,
                    'role' => 'leader',
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Team $team = null): array
    {
        // `team_members.user_id` unik, jadi user yang sudah masuk tim lain
        // tidak perlu ditawarkan lagi. Saat mengedit, anggota tim ini sendiri
        // tetap ditampilkan supaya bisa dilepas.
        $available = User::query()
            ->where(function ($q) use ($team) {
                $q->whereDoesntHave('teamMemberships');

                if ($team) {
                    $q->orWhereHas('teamMemberships', fn ($m) => $m->where('team_id', $team->getKey()));
                }
            })
            ->orderBy('name')
            ->get();

        return [
            'departments' => Department::ordered()->get(),
            // Leader hanya boleh diisi orang yang memang memimpin tim, agar
            // penunjukan leader tidak jatuh ke sembarang karyawan.
            'candidates' => User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['supervisor', 'project_leader', 'admin']))
                ->orderBy('name')
                ->get(),
            'availableUsers' => $available,
        ];
    }

    private function uniqueSlug(string $name, ?Team $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Team::where('slug', $slug)->whereKeyNot($ignore?->getKey() ?? 0)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
