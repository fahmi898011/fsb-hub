<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\PdfExportService;
use App\Traits\SanitizesSearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman Audit Log (Admin).
 *
 * PRD §16 — Admin bertanggung jawab atas tata kelola sistem, termasuk
 * menelusuri jejak perubahan data (siapa, kapan, apa yang berubah).
 *
 * Halaman ini read-only: tidak ada aksi create/update/delete.
 * Akses dijaga oleh permission `user.view` karena audit melekat pada
 * tata kelola akun & data (satu-satunya permission admin-access untuk
 * tata kelola saat ini).
 *
 * AGENT: asisten | 2026-10-10 | saring modul/hasil + pencarian bebas + ekspor CSV | modul Audit Log, §11.15
 */
class AuditLogController extends Controller
{
    use SanitizesSearchTerm;

    public function __construct(private readonly PdfExportService $pdfExporter) {}

    /**
     * Daftar audit log dengan filter (pencarian bebas, aksi, modul, hasil,
     * pelaku, entitas, rentang tanggal).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $this->filters($request);

        $logs = $this->query($filters)
            ->with('user')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Daftar nilai unik untuk mengisi dropdown filter.
        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $modules = AuditLog::query()
            ->whereNotNull('module')
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        $results = AuditLog::query()
            ->whereNotNull('result')
            ->select('result')
            ->distinct()
            ->orderBy('result')
            ->pluck('result');

        $subjects = AuditLog::query()
            ->whereNotNull('subject_type')
            ->select('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type');

        $users = User::query()
            ->whereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.audit.index', compact('logs', 'filters', 'actions', 'modules', 'results', 'subjects', 'users'));
    }

    /**
     * Ekspor hasil yang sedang disaring ke CSV.
     *
     * Memakai `chunk(500)` supaya ribuan baris tidak dimuat sekaligus ke
     * memori, dan BOM UTF-8 supaya Excel di Windows membaca huruf beraksen
     * dengan benar (pola yang sama dipakai ekspor laporan QA).
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', User::class);

        $filters = $this->filters($request);

        $query = $this->query($filters)->with('user')->latest();

        $filename = 'audit-log-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Waktu', 'Pelaku', 'Email pelaku', 'Peran', 'Modul', 'Aksi',
                'Deskripsi', 'Entitas', 'Label entitas', 'Kolom berubah',
                'Hasil', 'Tingkat', 'IP', 'Metode', 'Rute', 'Status', 'URL',
            ]);

            $query->chunk(500, function ($logs) use ($out): void {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $log->actorLabel(),
                        $log->actorEmail(),
                        $log->actor_role,
                        $log->module,
                        $log->action,
                        $log->description,
                        $log->subjectTypeLabel(),
                        $log->subject_label,
                        implode(', ', $log->changedFieldList()),
                        $log->result,
                        $log->severity,
                        $log->ip_address,
                        $log->method,
                        $log->route_name,
                        $log->status_code,
                        $log->url,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Ekspor PDF rekap audit sesuai filter yang aktif.
     *
     * AGENT: asisten | 2026-10-16 | tambah ekspor PDF | modul Audit Log
     *
     * CSV cocok untuk diolah lagi, PDF cocok untuk dilampirkan pada laporan
     * atau dibawa ke rapat. Keduanya memakai query yang SAMA (`query()`), jadi
     * angka di kedua format tidak mungkin berbeda.
     *
     * Dibatasi 2.000 entri terbaru: audit log bisa ratusan ribu baris, dan PDF
     * sebesar itu tidak lagi berguna untuk dibaca.
     */
    public function pdf(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $this->filters($request);

        $logs = $this->query($filters)
            ->with('user')
            ->latest()
            ->limit(2000)
            ->get();

        return $this->pdfExporter->download(
            'pdf.audit-log',
            [
                'logs' => $logs,
                'filters' => $filters,
                'filterSummary' => $this->filterSummary($filters),
                'truncated' => $logs->count() >= 2000,
            ],
            $this->pdfExporter->filename('audit-log'),
            [
                'title' => 'Rekap Audit Log',
                'orientation' => 'landscape',
            ],
        );
    }

    /**
     * Ringkasan filter untuk kepala dokumen PDF.
     *
     * @param  array<string, string>  $filters
     */
    private function filterSummary(array $filters): string
    {
        $parts = [];

        foreach (['q' => 'Kata kunci', 'action' => 'Aksi', 'module' => 'Modul', 'result' => 'Hasil'] as $key => $label) {
            if (($filters[$key] ?? '') !== '') {
                $parts[] = $label.': '.$filters[$key];
            }
        }

        if (($filters['from'] ?? '') !== '' || ($filters['to'] ?? '') !== '') {
            $parts[] = 'Rentang: '.($filters['from'] ?: '—').' s/d '.($filters['to'] ?: '—');
        }

        return $parts === [] ? 'Tanpa filter' : implode(' · ', $parts);
    }

    /**
     * Detail satu entri audit — konteks eksekusi + diff old → new values.
     */
    public function show(AuditLog $audit): View
    {
        $this->authorize('viewAny', User::class);

        // `subject` bukan relasi (alias diresolusi lokal di `AuditLog`), jadi
        // tidak bisa di-eager load; view memakai `subject_type` + labelnya.
        $audit->load('user');

        return view('admin.audit.show', ['log' => $audit]);
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        return [
            'q' => $this->sanitizeSearchTerm((string) $request->query('q', '')),
            'action' => trim((string) $request->query('action', '')),
            'module' => trim((string) $request->query('module', '')),
            'result' => trim((string) $request->query('result', '')),
            'user' => trim((string) $request->query('user', '')),
            'subject' => trim((string) $request->query('subject', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];
    }

    /**
     * Kueri bersama untuk daftar & ekspor supaya keduanya tidak menyimpang.
     *
     * @param  array<string, string>  $filters
     */
    private function query(array $filters): Builder
    {
        return AuditLog::query()
            // Pencarian bebas: satu kotak untuk deskripsi, label entitas,
            // potret pelaku, dan nama aksi.
            ->when($filters['q'] !== '', function (Builder $q) use ($filters) {
                $term = $filters['q'];

                $q->where(function (Builder $inner) use ($term) {
                    foreach (['description', 'subject_label', 'actor_name', 'actor_email', 'action'] as $column) {
                        $inner->orWhere($column, 'like', '%'.$term.'%');
                    }
                });
            })
            ->when($filters['action'] !== '', fn (Builder $q) => $q->where('action', 'like', $filters['action'].'%'))
            ->when($filters['module'] !== '', function (Builder $q) use ($filters) {
                // Cocokkan kolom `module` (baris baru) ATAU prefiks aksinya
                // (baris lama yang kolomnya masih NULL).
                $module = $filters['module'];

                $q->where(function (Builder $inner) use ($module) {
                    $inner->where('module', $module)
                        ->orWhere('action', 'like', $module.'.%');
                });
            })
            ->when($filters['result'] !== '', fn (Builder $q) => $q->where('result', $filters['result']))
            ->when($filters['user'] !== '', fn (Builder $q) => $q->where('user_id', (int) $filters['user']))
            ->when($filters['subject'] !== '', function (Builder $q) use ($filters) {
                // Menerima alias (`information_item`), nama pendek
                // (`InformationItem`), maupun FQCN — baris lama dan baris baru
                // sama-sama terjaring.
                $q->whereIn('subject_type', AuditLog::resolveSubjectTypes($filters['subject']));
            })
            ->when($filters['from'] !== '', fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['to']));
    }
}
