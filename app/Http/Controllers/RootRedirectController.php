<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Titik masuk "/" — mengarahkan pengunjung ke tempat yang tepat.
 *
 * Sebelumnya rute ini berupa closure di `routes/web.php`. Rute berbasis closure
 * membuat `php artisan route:cache` gagal ("Uses Closure"), padahal langkah itu
 * bagian dari rutinitas rilis di server. Isi pengalihannya tidak berubah.
 * AGENT: asisten | 2026-10-27 | controller pengalih "/" | persiapan rilis produksi
 */
class RootRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return auth()->check()
            ? redirect()->route('home')
            : redirect()->route('login');
    }
}