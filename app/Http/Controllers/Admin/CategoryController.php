<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InformationCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Master Informasi — pengelolaan struktur kategori.
 *
 * PRD §6 — Kategori Informasi adalah tanggung jawab Admin (Application
 * Management), bukan QA. Memindahkan controller ini dari namespace Qa ke Admin
 * sekaligus menghapus pelanggaran §7 ("Jangan membuat QA mengelola struktur
 * kategori jika itu merupakan tanggung jawab Admin").
 */
class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = InformationCategory::withCount('items')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.create', ['category' => new InformationCategory()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();

        InformationCategory::create($data);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Kategori berhasil dibuat.');
    }

    public function edit(InformationCategory $category): View
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, InformationCategory $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        $data['updated_by'] = Auth::id();

        $category->update($data);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(InformationCategory $category): RedirectResponse
    {
        if ($category->items()->exists()) {
            return back()->with('error', 'Kategori masih memiliki informasi. Pindahkan terlebih dahulu.');
        }

        $category->delete();

        return back()->with('success', 'Kategori berhasil dihapus.');
    }

    private function validated(Request $request, ?InformationCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! $category || $category->name !== $data['name']) {
            $slug = \Illuminate\Support\Str::slug($data['name']);
            $exists = InformationCategory::where('slug', $slug)
                ->when($category, fn ($q) => $q->whereKeyNot($category->getKey()))
                ->exists();

            if ($exists) {
                $slug .= '-' . mb_substr((string) now()->timestamp, -4);
            }

            $data['slug'] = $slug;
        }

        return $data;
    }
}
