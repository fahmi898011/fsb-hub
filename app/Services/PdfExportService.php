<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Pembungkus tunggal untuk ekspor PDF.
 *
 * AGENT: asisten | 2026-10-16 | buat berkas baru | modul Ekspor PDF
 *
 * Semua laporan memakai kelas ini supaya tiga hal tidak berbeda antar laporan:
 *
 * 1. Ukuran & orientasi kertas (A4).
 * 2. Footer: judul laporan di kiri, "Halaman X dari Y" di kanan. Nomor halaman
 *    dihitung dompdf, bukan disisipkan dari PHP, karena jumlah halaman baru
 *    diketahui setelah tata letak selesai.
 * 3. Nama berkas yang selalu memuat tanggal & jam, sehingga hasil ekspor
 *    beberapa kali tidak saling menimpa di folder unduhan pengguna.
 *
 * Catatan teknis: `render()` dipanggil SEKALI, lalu `download()`. Bila `render()`
 * dipanggil dua kali, kanvas akan digambar ulang dan teks footer hilang.
 */
class PdfExportService
{
    /**
     * Render sebuah view Blade menjadi unduhan PDF.
     *
     * @param  array<string, mixed>  $data
     * @param  array{title?: string, orientation?: string, paper?: string}  $options
     */
    public function download(string $view, array $data, string $filename, array $options = []): Response
    {
        $title = $options['title'] ?? 'Laporan';
        $orientation = $options['orientation'] ?? 'portrait';
        $paper = $options['paper'] ?? 'a4';

        $pdf = Pdf::loadView($view, $data + [
            'reportTitle' => $title,
            'generatedAt' => now(),
        ])->setPaper($paper, $orientation);

        // Berkas lokal saja: laporan tidak perlu menarik gambar dari internet,
        // dan mematikannya menutup celah SSRF lewat HTML yang dirender.
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        $pdf->render();

        $this->stampFooter($pdf->getDomPDF(), $title);

        return $pdf->download($filename);
    }

    /**
     * Tulis footer di setiap halaman.
     *
     * Dipisah dari `download()` agar mudah diuji/diubah tanpa menyentuh alur
     * render.
     */
    private function stampFooter(object $dompdf, string $title): void
    {
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        // Helvetica adalah font inti PDF: selalu tersedia tanpa perlu
        // registrasi, sehingga footer tidak pernah gagal karena font hilang.
        $font = $fontMetrics->getFont('helvetica', 'normal');

        $gray = [0.42, 0.45, 0.50];
        $size = 7.5;
        $baseline = $canvas->get_height() - 24;

        $canvas->page_text(32, $baseline, Str::limit(Str::ascii($title), 90), $font, $size, $gray);

        $canvas->page_text(
            $canvas->get_width() - 132,
            $baseline,
            'Halaman {PAGE_NUM} dari {PAGE_COUNT}',
            $font,
            $size,
            $gray,
        );
    }

    /**
     * Nama berkas aman: tanpa spasi/karakter khusus, selalu berakhiran `.pdf`.
     * Tanggal & jam disertakan agar ekspor berulang tidak saling menimpa.
     */
    public function filename(string $prefix): string
    {
        return Str::slug($prefix).'-'.now()->format('Ymd-His').'.pdf';
    }
}
