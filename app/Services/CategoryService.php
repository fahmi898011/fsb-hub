<?php

namespace App\Services;

use App\Models\InformationCategory;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    /**
     * Kategori aktif beserta jumlah dokumen TERBIT di dalamnya.
     *
     * `items_count` dihitung dari dokumen `published` saja: angka di kartu
     * kategori dipakai sebagai janji "sekian dokumen bisa dibaca", jadi draft
     * dan arsip tidak boleh ikut terhitung. Tanpa `withCount`, view hanya
     * mendapat null dan bagian itu tidak pernah tampil.
     */
    public function active(): Collection
    {
        return InformationCategory::query()
            ->active()
            ->withCount(['items' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->get();
    }

    public function all(): Collection
    {
        return InformationCategory::query()
            ->withCount('items')
            ->orderBy('sort_order')
            ->get();
    }

    public function findBySlug(string $slug): InformationCategory
    {
        return InformationCategory::where('slug', $slug)->firstOrFail();
    }
}
