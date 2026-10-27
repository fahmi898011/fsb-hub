<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel untuk impor data karyawan.
 *
 * Baris contoh diisi dari akun yang sudah ada bila tersedia, supaya admin
 * melihat format yang benar tanpa harus membaca dokumentasi.
 */
class EmployeeTemplateExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * Judul kolom. Urutan kolom tidak masalah saat impor karena pembacaan
     * memakai nama judul (WithHeadingRow), bukan posisi.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'nama',
            'email',
            'nip',
            'departemen',
            'telepon',
            'role',
            'status',
            'password',
        ];
    }

    /**
     * @return Collection<int, array<int, string|null>>
     */
    public function collection(): Collection
    {
        $sample = User::query()->orderBy('id')->first();

        // Satu baris contoh yang jelas-jelas contoh, agar admin tidak
        // tanpa sengaja mengimpor data palsu.
        return collect([
            [
                $sample?->name ? $sample->name . ' (contoh)' : 'Budi Karyawan',
                'karyawan.baru@fsb.test',
                'EMP-999',
                'Call Center',
                '081234567890',
                'employee',
                'active',
                'password',
            ],
        ]);
    }

    public function title(): string
    {
        return 'Karyawan';
    }

    /**
     * Baris judul dibuat tebal + latar abu agar mudah dibedakan.
     *
     * @return array<string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ];
    }
}
