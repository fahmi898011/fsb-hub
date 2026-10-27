<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EmployeeTemplateExport;
use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\EmployeeImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Impor data karyawan secara massal dari berkas Excel/CSV.
 *
 * Alur: unggah -> pratinjau (validasi) -> konfirmasi simpan.
 * Hasil pratinjau dititipkan di session, bukan dihitung ulang saat simpan,
 * supaya yang disimpan persis sama dengan yang dilihat admin.
 */
class EmployeeImportController extends Controller
{
    /** Kunci session penampung hasil pratinjau. */
    private const SESSION_KEY = 'employee_import_rows';

    public function __construct(
        private readonly EmployeeImportService $imports,
        private readonly AuditLogService $audit,
    ) {}

    public function create(): View
    {
        $this->authorize('create', \App\Models\User::class);

        return view('admin.users.import');
    }

    public function template(): BinaryFileResponse
    {
        $this->authorize('create', \App\Models\User::class);

        return Excel::download(new EmployeeTemplateExport, 'template-impor-karyawan.xlsx');
    }

    /**
     * Unggah berkas lalu tampilkan hasil validasi (belum menyimpan apa pun).
     */
    public function preview(Request $request): View|RedirectResponse
    {
        $this->authorize('create', \App\Models\User::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'file.required' => 'Pilih berkas terlebih dahulu.',
            'file.mimes' => 'Berkas harus berformat Excel (.xlsx, .xls) atau CSV.',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        $result = $this->imports->analyze($request->file('file')->getRealPath());

        // Tidak ada satu pun kolom wajib: berkasnya jelas salah, jangan
        // lanjutkan ke pratinjau agar admin tidak tertipu tabel kosong.
        if ($result['total'] === 0 || $result['missing_columns'] !== []) {
            return back()->withErrors([
                'file' => $result['missing_columns'] === []
                    ? 'Berkas tidak berisi data karyawan.'
                    : 'Kolom wajib tidak ditemukan: ' . implode(', ', $result['missing_columns']) . '.',
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $result['rows']);

        return view('admin.users.import', [
            'result' => $result,
            'filename' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    /**
     * Simpan baris valid. Ditolak bila masih ada baris yang tidak valid.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\User::class);

        $rows = $request->session()->pull(self::SESSION_KEY);

        if (! is_array($rows) || $rows === []) {
            return redirect()
                ->route('admin.users.import')
                ->withErrors(['file' => 'Sesi pratinjau sudah berakhir. Silakan unggah ulang berkas.']);
        }

        $invalid = collect($rows)->where('status', 'invalid')->count();

        if ($invalid > 0) {
            return redirect()
                ->route('admin.users.import')
                ->withErrors([
                    'file' => "Masih ada {$invalid} baris yang tidak valid. Perbaiki berkas lalu unggah ulang.",
                ]);
        }

        $created = $this->imports->commit($rows);

        $this->audit->log('user.import', null, [], ['jumlah' => $created]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Berhasil mengimpor {$created} karyawan.");
    }
}
