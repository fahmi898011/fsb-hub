<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Bentrok penyuntingan catatan kerja (dua PC / dua tab).
 *
 * AGENT: asisten | 2026-10-01 | buat berkas baru | modul Catatan Kerja, fase 1 backend
 *
 * Dilempar oleh `WorkNoteService::saveChanges()` ketika versi yang dipegang
 * client sudah tidak sama dengan versi di database. Sengaja berupa exception,
 * bukan nilai `false`, supaya tidak ada pemanggil yang lupa memeriksanya:
 * bila tidak ditangkap, permintaan GAGAL - bukan menimpa data diam-diam.
 *
 * `render()` mengembalikan 409 + versi terbaru supaya sisi klien dapat
 * menampilkan pesan yang jelas dan menawarkan muat ulang.
 */
class NoteConflictException extends RuntimeException
{
    public function __construct(public readonly int $currentVersion)
    {
        parent::__construct('Catatan ini sudah diperbarui di tempat lain.');
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => 'Catatan ini sudah diperbarui di tempat lain. '
                . 'Muat ulang halaman untuk melihat versi terbaru agar tidak bertimpa.',
            'current_version' => $this->currentVersion,
        ], 409);
    }
}
