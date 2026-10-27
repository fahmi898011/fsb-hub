<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\DocumentView;
use App\Models\InformationItem;
use App\Services\CategoryService;
use App\Services\DocumentService;
use App\Services\InformationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InformationController extends Controller
{
    public function __construct(
        private readonly InformationService $information,
        private readonly CategoryService $categories,
        private readonly DocumentService $documents,
    ) {}

    /**
     * Halaman semua informasi.
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['category', 'status', 'search', 'sort']);
        $filters['only_published'] = true;

        $items = $this->information->paginate($filters);
        $categories = $this->categories->active();

        return view('information.index', compact('items', 'categories', 'filters'));
    }

    /**
     * Halaman per kategori (mis. /informasi/juknis).
     */
    public function category(Request $request, string $slug): View
    {
        $category = $this->categories->findBySlug($slug);

        $filters = $request->only(['search', 'sort']);
        $filters['category'] = $slug;
        $filters['only_published'] = true;

        $items = $this->information->paginate($filters);
        $categories = $this->categories->active();

        return view('information.category', compact('category', 'items', 'categories', 'filters'));
    }

    /**
     * Detail informasi + pencatatan view.
     */
    public function show(string $slug): View
    {
        $item = InformationItem::with(['category', 'versions.creator', 'currentVersion', 'tags'])
            ->withCount(['views', 'downloads'])
            ->where('slug', $slug)
            ->firstOrFail();

        $this->authorize('view', $item);

        // catat view (dedup sederhana: 1 view per user per hari)
        $alreadyViewed = DocumentView::where('information_id', $item->id)
            ->where('user_id', auth()->id())
            ->whereDate('viewed_at', today())
            ->exists();

        if (! $alreadyViewed) {
            DocumentView::create([
                'information_id' => $item->id,
                'version_id' => $item->current_version_id,
                'user_id' => auth()->id(),
                'viewed_at' => now(),
            ]);
        }

        // PRD §12 — dokumen terkait dalam kategori yang sama
        $related = $this->information->related($item);

        return view('information.show', compact('item', 'related'));
    }

    /**
     * Streaming dokumen (inline viewer).
     */
    public function document(string $slug): StreamedResponse
    {
        $item = InformationItem::with('currentVersion')->where('slug', $slug)->firstOrFail();

        $this->authorize('view', $item);

        $version = $item->currentVersion;
        abort_unless($version, 404, 'Dokumen belum tersedia.');

        return $this->documents->stream($version);
    }

    /**
     * Download dokumen + tracking.
     */
    public function download(string $slug): StreamedResponse
    {
        $item = InformationItem::with('currentVersion')->where('slug', $slug)->firstOrFail();

        $this->authorize('view', $item);

        $version = $item->currentVersion;
        abort_unless($version, 404, 'Dokumen belum tersedia.');

        Download::create([
            'information_id' => $item->id,
            'version_id' => $version->id,
            'user_id' => auth()->id(),
            'downloaded_at' => now(),
        ]);

        return $this->documents->stream($version, download: true);
    }
}
