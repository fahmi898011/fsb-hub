<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInformationRequest;
use App\Http\Requests\StoreVersionRequest;
use App\Http\Requests\UpdateInformationRequest;
use App\Models\InformationItem;
use App\Services\CategoryService;
use App\Services\InformationNotifier;
use App\Services\InformationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InformationController extends Controller
{
    public function __construct(
        private readonly InformationService $information,
        private readonly CategoryService $categories,
        private readonly InformationNotifier $notifier,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', InformationItem::class);

        $filters = $request->only(['category', 'status', 'search', 'sort']);
        $filters['only_published'] = false;

        $items = $this->information->paginate($filters);
        $categories = $this->categories->active();

        return view('qa.information.index', compact('items', 'categories', 'filters'));
    }

    public function create(): View
    {
        $this->authorize('create', InformationItem::class);

        $categories = $this->categories->active();

        return view('qa.information.create', compact('categories'));
    }

    public function store(StoreInformationRequest $request): RedirectResponse
    {
        $item = $this->information->create($request->validated());

        // Pemicu #1: informasi baru dibuat (draft) -> beri tahu QA lain.
        $this->notifier->created($item, $request->user());

        return redirect()
            ->route('qa.information.show', $item)
            ->with('success', 'Informasi berhasil dibuat.');
    }

    public function show(InformationItem $information): View
    {
        $this->authorize('view', $information);

        $information->load(['category', 'versions.creator', 'currentVersion', 'tags']);

        return view('qa.information.show', ['item' => $information]);
    }

    public function edit(InformationItem $information): View
    {
        $this->authorize('update', $information);

        $categories = $this->categories->active();
        $information->load(['tags']);

        return view('qa.information.edit', ['item' => $information, 'categories' => $categories]);
    }

    public function update(UpdateInformationRequest $request, InformationItem $information): RedirectResponse
    {
        $this->authorize('update', $information);

        $this->information->update($information, $request->validated());

        return redirect()
            ->route('qa.information.show', $information)
            ->with('success', 'Informasi berhasil diperbarui.');
    }

    public function publish(Request $request, InformationItem $information): RedirectResponse
    {
        $this->authorize('publish', $information);

        $wasPublished = $information->status === 'published';

        $this->information->publish($information);

        // Pemicu #2: baru berubah menjadi published -> beri tahu karyawan.
        // Publish ulang tidak memicu notifikasi ganda.
        if (! $wasPublished) {
            $this->notifier->published($information->fresh(), $request->user());
        }

        return back()->with('success', 'Informasi berhasil dipublikasikan.');
    }

    public function archive(InformationItem $information): RedirectResponse
    {
        $this->authorize('archive', $information);

        $this->information->archive($information);

        return back()->with('success', 'Informasi berhasil diarsipkan.');
    }

    public function storeVersion(StoreVersionRequest $request, InformationItem $information): RedirectResponse
    {
        $this->authorize('version', $information);

        $version = $this->information->addVersion($information, $request->validated());

        // Pemicu #3: versi baru diunggah -> beri tahu karyawan, tetapi hanya
        // bila dokumennya memang sudah published (draft belum terlihat mereka).
        $this->notifier->versionAdded($information->fresh(), $version, $request->user());

        return back()->with('success', 'Versi baru berhasil ditambahkan.');
    }
}
