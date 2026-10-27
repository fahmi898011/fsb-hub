<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga rute yang hanya boleh hidup saat saklar fitur menyala.
 *
 * AGENT: asisten | 2026-10-16 | buat berkas baru | modul Pengaturan Fitur
 *
 * Dipakai untuk modul yang DISEMBUNYIKAN, bukan dihapus — mis. Quiz pilihan
 * ganda lama. Menyembunyikan hanya di sidebar tidak cukup: URL lama yang sudah
 * di-bookmark atau dibagikan antar karyawan akan tetap terbuka. Middleware ini
 * menutup rutenya juga.
 *
 * Respons yang dipilih adalah 404, bukan 403: bagi pengguna, modul yang
 * dimatikan seharusnya "tidak ada", bukan "ada tetapi Anda dilarang". 403 justru
 * memberi tahu bahwa di baliknya ada sesuatu.
 *
 * Pemakaian di routes: `->middleware('feature:quiz.multiple_choice')`.
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature, ?string $default = null): Response
    {
        $fallback = $default === null ? false : (bool) (int) $default;

        abort_unless(Setting::enabled($feature, $fallback), 404);

        return $next($request);
    }
}
