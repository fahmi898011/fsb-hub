<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\DocumentView;
use App\Models\InformationItem;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Jendela waktu untuk angka aktivitas. Dashboard menjawab "apa yang
     * terjadi belakangan ini", bukan "berapa total sepanjang masa" — angka
     * seumur hidup tidak pernah berubah dan karena itu tidak bisa memicu
     * tindakan apa pun.
     */
    private const PERIOD_DAYS = 7;

    public function index(): View
    {
        $since = now()->subDays(self::PERIOD_DAYS);
        $previousSince = now()->subDays(self::PERIOD_DAYS * 2);

        $stats = [
            'total' => InformationItem::count(),
            'published' => InformationItem::published()->count(),
            'draft' => InformationItem::where('status', 'draft')->count(),
            'archived' => InformationItem::where('status', 'archived')->count(),
            'views' => DocumentView::where('viewed_at', '>=', $since)->count(),
            'downloads' => Download::where('downloaded_at', '>=', $since)->count(),
        ];

        // Pembanding minggu sebelumnya. Null berarti belum ada data untuk
        // dibandingkan — lebih jujur daripada menampilkan "+100%".
        $viewsTrend = $this->trend(
            $stats['views'],
            DocumentView::whereBetween('viewed_at', [$previousSince, $since])->count()
        );

        $downloadsTrend = $this->trend(
            $stats['downloads'],
            Download::whereBetween('downloaded_at', [$previousSince, $since])->count()
        );

        $oldestDraftDays = $this->daysSince(
            InformationItem::where('status', 'draft')->oldest('updated_at')->value('updated_at')
        );

        // Hanya dokumen terbit, sama seperti chip kategori di beranda.
        // Kalau dua halaman memakai label yang sama dengan angka berbeda,
        // kepercayaan pengguna ke keduanya ikut hilang.
        $byCategory = InformationItem::query()
            ->selectRaw('category_id, count(*) as total')
            ->where('status', 'published')
            ->with('category')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        $recent = InformationItem::with(['category'])
            ->latest('updated_at')
            ->limit(6)
            ->get();

        // Dilihat dan diunduh digabung dalam satu daftar: dua panel berdampingan
        // memakan dua kali ruang untuk satu wawasan yang sama.
        $topRead = InformationItem::query()
            ->with('category')
            ->withCount([
                'views' => fn ($query) => $query->where('viewed_at', '>=', $since),
                'downloads' => fn ($query) => $query->where('downloaded_at', '>=', $since),
            ])
            ->where(function ($query) use ($since) {
                $query->whereHas('views', fn ($sub) => $sub->where('viewed_at', '>=', $since))
                    ->orWhereHas('downloads', fn ($sub) => $sub->where('downloaded_at', '>=', $since));
            })
            ->orderByDesc('views_count')
            ->orderByDesc('downloads_count')
            ->limit(5)
            ->get();

        return view('qa.dashboard', compact(
            'stats',
            'viewsTrend',
            'downloadsTrend',
            'oldestDraftDays',
            'byCategory',
            'recent',
            'topRead'
        ));
    }

    /** Persentase perubahan; null bila tidak ada pembanding. */
    private function trend(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    /** Umur data dalam hari; null bila tidak ada data. */
    private function daysSince(?string $timestamp): ?int
    {
        if (! $timestamp) {
            return null;
        }

        return (int) floor(abs(now()->diffInDays($timestamp)));
    }
}

