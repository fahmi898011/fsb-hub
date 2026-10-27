<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * AGENT: asisten | 2026-10-10 | catat penghapusan akun ke audit | modul Audit Log, §11.15
     */
    public function __construct(
        protected ProfileService $profileService,
        protected AuditLogService $audit,
    ) {}

    public function edit()
    {
        $user = auth()->user();
        $activityLogs = $this->profileService->getActivityLogs();

        return view('profile.index', compact('user', 'activityLogs'));
    }

    public function index()
    {
        return $this->edit();
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . auth()->id(),
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'current_password' => 'nullable|required_with:password|current_password',
            'password' => 'nullable|required_with:current_password|string|min:8|confirmed',
        ]);

        $data = $request->only(['name', 'email', 'phone', 'department']);

        if ($request->hasFile('avatar')) {
            $this->profileService->updateAvatar($request->file('avatar'));
        }

        $this->profileService->updateProfile($data);

        if ($request->filled('password')) {
            $this->profileService->changePassword($request->password);
        }

        return redirect()->route('profile.edit')
            ->with('success', 'Profil berhasil diupdate.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $this->profileService->changePassword($request->password);

        return redirect()->route('profile.edit')->with('success', 'Password berhasil diubah.');
    }

    public function changePassword(Request $request)
    {
        return $this->updatePassword($request);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = auth()->user();

        // Dicatat SEBELUM akun dihapus. FK `audit_logs.user_id` memakai
        // `nullOnDelete`, jadi kolom itu akan dikosongkan database setelah
        // penghapusan — potret `actor_name`/`actor_email` inilah yang membuat
        // jejaknya tetap terbaca.
        $this->audit->log('auth.account_deleted', $user, [
            'name' => $user->name,
            'email' => $user->email,
        ], [], [
            'module' => 'auth',
            'category' => 'auth',
            'severity' => 'critical',
        ]);

        auth()->logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Akun berhasil dihapus.');
    }
}
