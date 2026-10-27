<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Halaman muka Admin (Application Management).
 *
 * AGENT: asisten | 2026-10-16 | buat berkas baru | modul Dashboard per peran
 *
 * Sebelum ini tidak ada rute `/admin`: mengetik alamat itu menghasilkan 404,
 * dan sidebar langsung melempar Admin ke "Master Informasi". Akibatnya satu-
 * satunya peran yang mengatur seluruh wadah aplikasi tidak punya tempat untuk
 * melihat keadaan wadah itu sendiri.
 *
 * Isi halaman dipilih dengan satu pertanyaan: "apa yang bisa SALAH dan hanya
 * Admin yang bisa membetulkannya?". Karena itu empat angkanya adalah akun
 * tanpa peran, akun nonaktif, perubahan data seminggu terakhir, dan tiket
 * yang belum bertuan — bukan jumlah artikel atau statistik lain yang sudah
 * punya halaman sendiri.
 */
class DashboardController extends Controller
{
    /** Jendela waktu jejak perubahan. */
    private const RECENT_DAYS = 7;

    public function index(): View
    {
        $since = now()->subDays(self::RECENT_DAYS);

        $stats = [
            'users' => User::count(),
            // Akun tanpa peran tidak bisa membuka menu apa pun: secara teknis
            // aktif, secara praktis tidak berguna. Inilah kesalahan impor yang
            // paling sering terjadi.
            'users_without_role' => User::doesntHave('roles')->count(),
            'inactive_users' => User::query()
                ->whereNotNull('status')
                ->where('status', '!=', 'active')
                ->count(),
            'changes' => AuditLog::query()->where('created_at', '>=', $since)->count(),
            'unassigned_tickets' => Ticket::query()->open()->whereNull('assigned_to')->count(),

            // Kelengkapan akun: tiga angka ini menjawab "apakah akun yang ada
            // benar-benar bisa dipakai?" — bukan sekadar berapa banyak akun.
            // AGENT: asisten | 2026-10-20 | tambah statistik kesiapan akun | modul Dashboard Admin
            'users_active' => User::query()->where('status', 'active')->count(),
            'users_with_role' => User::query()->has('roles')->count(),
            'users_with_division' => User::query()->whereNotNull('department_id')->count(),
            'departments' => Department::query()->count(),
            'teams' => Team::query()->count(),
            'teams_without_leader' => Team::query()->whereNull('leader_id')->count(),
        ];

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentChanges' => AuditLog::query()
                ->with('user')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(6)
                ->get(),
            'usersWithoutRole' => User::doesntHave('roles')
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'email']),
            'unassignedTickets' => Ticket::query()
                ->open()
                ->whereNull('assigned_to')
                ->orderBy('created_at')
                ->limit(5)
                ->get(),
            'roleCount' => Role::count(),
        ]);
    }
}
