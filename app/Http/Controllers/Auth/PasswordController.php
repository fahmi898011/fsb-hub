<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * AGENT: asisten | 2026-10-10 | catat perubahan kata sandi ke audit | modul Audit Log, §11.15
     */
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Kata sandi TIDAK pernah masuk log — hanya kejadiannya.
        $this->audit->log('auth.password_changed', $user, [], [], [
            'module' => 'auth',
            'category' => 'auth',
            'severity' => 'notice',
            'description' => 'Mengubah kata sandi akun',
        ]);

        return back()->with('status', 'password-updated');
    }
}
