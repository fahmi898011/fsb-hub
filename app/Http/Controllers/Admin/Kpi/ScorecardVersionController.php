<?php

namespace App\Http\Controllers\Admin\Kpi;

use App\Http\Controllers\Controller;
use App\Models\ScorecardVersion;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Workspace Versi Scorecard (Admin) - PRD Employee KPI/QA §6/§37.
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Halaman ini adalah "meja kerja" satu versi: menyusun kategori & parameter,
 * lalu memastikan total bobot 100% sebelum versi diaktifkan. Aturan PRD §6
 * dipegang di sini: versi yang SUDAH aktif tidak boleh disunting isinya -
 * buat versi baru bila bobot perlu berubah.
 */
class ScorecardVersionController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function show(ScorecardVersion $version): View
    {
        $this->authorize('view', $version);

        $version->load('scorecard.kpi', 'scorecard.channel');

        $categories = $version->categories()
            ->with(['parameters' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return view('admin.kpi.versions.show', [
            'version' => $version,
            'categories' => $categories,
            'totalWeight' => $version->totalWeight(),
        ]);
    }

    /**
     * Aktifkan versi. Dua penjaga: total bobot harus 100% (PRD §37) dan versi
     * ini menonaktifkan versi `active` lain pada scorecard yang sama supaya
     * hanya ada SATU versi aktif per scorecard.
     */
    public function activate(ScorecardVersion $version): RedirectResponse
    {
        $this->authorize('update', $version);

        $total = $version->totalWeight();

        if (round($total, 2) !== 100.00) {
            return back()->with('error', sprintf(
                'Total bobot harus 100%% sebelum versi diaktifkan (saat ini %.2f%%).',
                $total,
            ));
        }

        $version->scorecard->versions()
            ->whereKeyNot($version->getKey())
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        $version->update([
            'status' => 'active',
            'published_at' => $version->published_at ?? now(),
            'updated_by' => Auth::id(),
        ]);

        $this->audit->log('kpi.scorecard.version.activate', $version, ['status' => 'draft'], [
            'status' => 'active',
            'version' => $version->version,
        ]);

        return back()->with('success', "Versi {$version->version} kini aktif.");
    }

    /**
     * Kembalikan versi aktif menjadi draft (mis. salah aktifkan). Versi yang
     * sudah dipakai assessment tidak boleh diubah - penjagaannya menyusul di
     * Fase 2 saat tabel assessment ada.
     */
    public function deactivate(ScorecardVersion $version): RedirectResponse
    {
        $this->authorize('update', $version);

        $version->update(['status' => 'draft', 'updated_by' => Auth::id()]);

        $this->audit->log('kpi.scorecard.version.deactivate', $version, ['status' => 'active'], [
            'status' => 'draft',
            'version' => $version->version,
        ]);

        return back()->with('success', "Versi {$version->version} dinonaktifkan (kembali draft).");
    }

    public function destroy(ScorecardVersion $version): RedirectResponse
    {
        $this->authorize('delete', $version);

        $scorecard = $version->scorecard;
        $number = $version->version;

        $version->delete();

        $this->audit->log('kpi.scorecard.version.delete', $scorecard, ['version' => $number], []);

        return redirect()
            ->route('admin.kpi.scorecards.show', $scorecard)
            ->with('success', "Versi {$number} berhasil dihapus.");
    }
}
