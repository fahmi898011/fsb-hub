<?php

namespace App\Services;

use App\Models\InformationItem;
use App\Traits\SanitizesSearchTerm;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SearchService
{
    use SanitizesSearchTerm;

    /**
     * Pencarian informasi berdasarkan judul, deskripsi, nomor dokumen,
     * nama kategori, dan tag (Phase 1: MySQL LIKE).
     */
    public function search(string $term, int $perPage = 20, ?string $categorySlug = null): LengthAwarePaginator
    {
        $term = $this->sanitizeSearchTerm($term);
        $categorySlug = $categorySlug !== null && $categorySlug !== '' ? $categorySlug : null;

        $query = InformationItem::query()
            ->published()
            ->with(['category', 'currentVersion', 'tags'])
            ->withCount(['views', 'downloads'])
            ->when($categorySlug, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $categorySlug)));

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('document_number', 'like', "%{$term}%")
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', "%{$term}%"));
            });
        }

        return $query
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
