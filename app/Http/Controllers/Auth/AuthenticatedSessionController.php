<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * AGENT: asisten | 2026-10-10 | catat masuk/keluar ke audit | modul Audit Log, §11.15
     */
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Masuk yang berhasil dicatat SETELAH sesi diperbarui supaya pelakunya
        // sudah terpasang di guard. Percobaan yang GAGAL dicatat di
        // LoginRequest::authenticate() (di sana tidak ada pelaku yang sah).
        $this->audit->log('auth.login', Auth::user(), [], [], [
            'module' => 'auth',
            'category' => 'auth',
            'severity' => 'notice',
            'description' => 'Masuk ke aplikasi',
        ]);

        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Dicatat SEBELUM logout: setelah `Auth::logout()` id pelakunya hilang.
        $user = Auth::user();

        if ($user !== null) {
            $this->audit->log('auth.logout', $user, [], [], [
                'module' => 'auth',
                'category' => 'auth',
                'severity' => 'notice',
                'description' => 'Keluar dari aplikasi',
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
