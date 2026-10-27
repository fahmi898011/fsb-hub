<?php

namespace App\Http\Controllers;

use App\Models\InformationCategory;
use App\Models\InformationItem;
use App\Services\CategoryService;
use App\Services\HomeTaskService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly HomeTaskService $tasks,
    ) {}

    public function index(Request $request): View
    {
        $categories = $this->categories->active();
        $announcements = $this->announcements();

        // Pengumuman sudah tampil di seksinya sendiri. Sebelumnya judul yang
        // sama muncul lagi di "Informasi terbaru", sehingga satu layar
        // menampilkan dokumen yang sama dua kali.
        $latest = InformationItem::published()
            ->with('category')
            // `downloads_count` dipakai kartu dokumen; tanpa hitungan ini
            // setiap dokumen selalu tampil "0 unduhan".
            ->withCount(['views', 'downloads'])
            ->when(
                $announcements->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $announcements->pluck('id')),
            )
            ->latestPublished()
            ->limit(6)
            ->get();

        // `whereHas('views')` wajib: tanpa itu daftar "paling banyak dibuka"
        // diisi dokumen ber-nol view dan judulnya tidak lagi benar.
        // `currentVersion` sengaja tidak di-eager-load — view tidak memakainya
        // dan setiap relasi menambah satu query per render.
        $popular = InformationItem::published()
            ->with('category')
            ->withCount('views')
            ->whereHas('views')
            ->orderByDesc('views_count')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('home', [
            'categories' => $categories,
            'latest' => $latest,
            'popular' => $popular,
            'announcements' => $announcements,
            // Selalu bilangan bulat: view memakai perbandingan `!== null`,
            // bukan truthiness, supaya "0 dokumen" tetap tampil saat kosong.
            'totalPublished' => InformationItem::published()->count(),
            'tasks' => $this->tasks->forUser($request->user()),
        ]);
    }

    /**
     * Tiga pengumuman terbaru dari kategori khusus `announcement`.
     *
     * Bila kategori itu belum dibuat, kembalikan koleksi kosong — Beranda
     * cukup tidak menampilkan seksinya, bukan menampilkan judul kosong.
     *
     * @return Collection<int, InformationItem>
     */
    private function announcements(): Collection
    {
        $categoryId = InformationCategory::query()
            ->where('slug', InformationCategory::ANNOUNCEMENT_SLUG)
            ->value('id');

        if ($categoryId === null) {
            return new Collection;
        }

        return InformationItem::published()
            ->with('category')
            ->where('category_id', $categoryId)
            ->latestPublished()
            ->limit(3)
            ->get();
    }
}

