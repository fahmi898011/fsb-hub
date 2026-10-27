<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Konteks eksekusi untuk jejak audit (PRD §16).
 *
 * AGENT: asisten | 2026-10-10 | middleware request_id + status_code | modul Audit Log, §11.15
 *
 * Dua tugas:
 * 1. `handle()` menandai permintaan web dengan `request_id` supaya semua entri
 *    audit yang lahir dari SATU permintaan bisa dikenali sebagai satu kejadian
 *    (dan `AuditLogService` memakai penanda ini sebagai syarat mengisi konteks
 *    HTTP — di luar permintaan web, kolom method/rute/URL dibiarkan kosong).
 * 2. `terminate()` melengkapi `status_code` SETELAH respons diketahui, karena
 *    saat controller menulis log responsnya belum ada.
 */
class AuditHttpRequest
{
    /** Nama atribut permintaan yang menyimpan id permintaan audit. */
    public const REQUEST_ID_ATTRIBUTE = 'audit_request_id';

    /**
     * Penanda bahwa permintaan ini BENAR-BENAR menulis entri audit. Dipakai
     * `terminate()` supaya permintaan yang tidak menulis apa pun (mayoritas)
     * tidak membayar satu perintah UPDATE.
     */
    public const AUDIT_WRITTEN_ATTRIBUTE = 'audit_written';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, (string) Str::uuid());

        return $next($request);
    }

    /**
     * Satu-satunya mutasi yang diizinkan atas `audit_logs`: menaikkan
     * `status_code` dari NULL. Ditulis lewat query builder (bukan model) agar
     * penjaga append-only di `AuditLog` tidak dilanggar dan `updated_at` tidak
     * ikut berubah.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Permintaan yang tidak menulis entri audit tidak perlu di-UPDATE:
        // ini yang membuat middleware ini nyaris tanpa biaya.
        if (! $request->attributes->get(self::AUDIT_WRITTEN_ATTRIBUTE)) {
            return;
        }

        $requestId = $request->attributes->get(self::REQUEST_ID_ATTRIBUTE);

        if (! is_string($requestId) || $requestId === '') {
            return;
        }

        try {
            DB::table('audit_logs')
                ->where('request_id', $requestId)
                ->whereNull('status_code')
                ->update(['status_code' => $response->getStatusCode()]);
        } catch (Throwable) {
            // Jejak audit tidak boleh menggagalkan permintaan pengguna.
        }
    }
}
