<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Membaca berkas Excel/CSV karyawan menjadi array baris mentah.
 *
 * Kelas ini sengaja TIDAK menyentuh database sama sekali: tugasnya hanya
 * membaca. Validasi dan penyimpanan ditangani karyawan ImportService, supaya
 * admin bisa melihat pratinjau (mana baris yang valid, mana yang ditolak)
 * sebelum benar-benar menekan tombol impor.
 */
class EmployeeImport implements SkipsEmptyRows, WithHeadingRow
{
    /** Baris pertama (indeks 0) = judul kolom. */
    public function headingRow(): int
    {
        return 1;
    }
}
