<?php

namespace App\Traits;

/**
 * Membuang karakter wildcard SQL (% dan _)
 * agar input pencarian tidak bisa dipakai
 * untuk "wildcard injection" / DoS ringan.
 */
trait SanitizesSearchTerm
{
    protected function sanitizeSearchTerm(?string $term): string
    {
        if ($term === null) {
            return '';
        }

        // Buang escape char & wildcard, lalu potong spasi berlebih
        $clean = str_replace(['\\', '%', '_'], ' ', $term);

        return trim(preg_replace('/\s+/', ' ', $clean));
    }
}