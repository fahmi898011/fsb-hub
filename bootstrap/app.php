<?php

use App\Http\Middleware\AuditHttpRequest;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Services\AuditLogService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Jejak eksekusi audit (PRD §16): menandai setiap permintaan web dengan
        // `request_id` dan melengkapi `status_code` setelah respons diketahui.
        // AGENT: asisten | 2026-10-10 | middleware jejak audit | modul Audit Log, §11.15
        $middleware->appendToGroup('web', AuditHttpRequest::class);

        // Alias `feature:nama.saklar` untuk modul yang bisa dinyalakan/dimatikan
        // dari halaman Pengaturan Fitur (lihat App\Models\Setting).
        // AGENT: asisten | 2026-10-16 | alias middleware feature | modul Pengaturan Fitur
        $middleware->alias([
            'feature' => EnsureFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Akses ditolak (403) ikut dicatat: siapa, ke rute mana, kapan.
        // `AuthorizationException` dari policy / middleware `can:` sudah
        // diubah handler menjadi `AccessDeniedHttpException` sebelum callback
        // ini dipanggil, jadi cukup memeriksa status pada HttpExceptionInterface.
        // Callback ini mengembalikan null supaya render bawaan Laravel tetap
        // dipakai (halaman 403 standar).
        // AGENT: asisten | 2026-10-10 | catat 403 | modul Audit Log, §11.15
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 403 || ! Auth::check()) {
                return null;
            }

            try {
                app(AuditLogService::class)->log('access.denied', null, [], [], [
                    'module' => 'access',
                    'category' => 'access',
                    'severity' => 'critical',
                    'result' => 'denied',
                    'subject_label' => $request->path(),
                    'description' => 'Akses ditolak: '.$request->getMethod().' /'.$request->path(),
                ]);
            } catch (\Throwable) {
                // Jejak audit tidak boleh menggagalkan respons error.
            }

            return null;
        });
    })->create();

