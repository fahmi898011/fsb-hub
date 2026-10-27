<?php

namespace App\Services;

use App\Models\InformationItem;
use App\Models\InformationVersion;
use App\Traits\SanitizesSearchTerm;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InformationService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly DocumentService $documents,
    ) {}

    /**
     * Daftar informasi (Employee: hanya published).
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = InformationItem::query()
            ->with(['category', 'currentVersion', 'tags'])
            ->withCount(['views', 'downloads']);

        if (($filters['only_published'] ?? true) === true) {
            $query->published();
        }

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('document_number', 'like', "%{$term}%");
            });
        }

        $sort = $filters['sort'] ?? 'latest';
        match ($sort) {
            'popular' => $query->orderByDesc('views_count'),
            'az' => $query->orderBy('title'),
            default => $query->orderByDesc('published_at')->orderByDesc('created_at'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * PRD §12 — Informasi terkait: dokumen published lain dalam kategori yang
     * sama, diurutkan berdasarkan popularitas lalu kebaruan.
     */
    public function related(InformationItem $item, int $limit = 5): \Illuminate\Support\Collection
    {
        if (! $item->category_id) {
            return collect();
        }

        return InformationItem::query()
            ->published()
            ->where('category_id', $item->category_id)
            ->whereKeyNot($item->getKey())
            ->with('category')
            ->withCount('views')
            ->orderByDesc('views_count')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): InformationItem
    {
        return DB::transaction(function () use ($data) {
            $item = InformationItem::create([
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['title']),
                'document_number' => $data['document_number'] ?? null,
                'description' => $data['description'] ?? null,
                'content' => $data['content'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'published_at' => ($data['status'] ?? 'draft') === 'published' ? now() : null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if (! empty($data['document'])) {
                $version = $this->documents->storeVersion(
                    $item,
                    $data['document'],
                    $data['version'] ?? '1.0',
                    $data['change_notes'] ?? null,
                );
                $item->update(['current_version_id' => $version->id]);
            }

            if (! empty($data['tags'])) {
                $this->syncTags($item, $data['tags']);
            }

            // Nama aksi diberi prefiks modul (PRD §16): tanpa itu dropdown
            // filter berisi `created`/`updated` yang tidak bisa dibedakan dari
            // modul lain.
            // AGENT: asisten | 2026-10-10 | nama aksi berprefiks modul | modul Audit Log, §11.15
            $this->audit->log('information.created', $item, [], $item->toArray());

            return $item;
        });
    }

    public function update(InformationItem $item, array $data): InformationItem
    {
        return DB::transaction(function () use ($item, $data) {
            $old = $item->toArray();

            $item->update([
                'category_id' => $data['category_id'] ?? $item->category_id,
                'title' => $data['title'] ?? $item->title,
                'document_number' => $data['document_number'] ?? $item->document_number,
                'description' => $data['description'] ?? $item->description,
                'content' => $data['content'] ?? $item->content,
                'updated_by' => auth()->id(),
            ]);

            if (! empty($data['tags'])) {
                $this->syncTags($item, $data['tags']);
            }

            // `logChanges()` mencatat HANYA kolom yang berubah (bukan seluruh
            // baris), sehingga diff di halaman detail tetap terbaca.
            // AGENT: asisten | 2026-10-10 | diff kolom saja | modul Audit Log, §11.15
            $this->audit->logChanges('information.updated', $item, $old);

            return $item;
        });
    }

    public function publish(InformationItem $item): InformationItem
    {
        // Status SEBELUM diubah ikut dicatat: tanpa itu jejaknya hanya
        // "menjadi published" tanpa tahu asalnya dari mana.
        // AGENT: asisten | 2026-10-10 | catat status awal | modul Audit Log, §11.15
        $before = ['status' => $item->status];

        $item->update([
            'status' => 'published',
            'published_at' => $item->published_at ?? now(),
            'updated_by' => auth()->id(),
        ]);

        $this->audit->log('information.published', $item, $before, ['status' => 'published']);

        return $item;
    }

    public function archive(InformationItem $item): InformationItem
    {
        $before = ['status' => $item->status];

        $item->update(['status' => 'archived', 'updated_by' => auth()->id()]);

        $this->audit->log('information.archived', $item, $before, ['status' => 'archived']);

        return $item;
    }

    public function addVersion(InformationItem $item, array $data): InformationVersion
    {
        return DB::transaction(function () use ($item, $data) {
            $version = $this->documents->storeVersion(
                $item,
                $data['document'],
                $data['version'],
                $data['change_notes'] ?? null,
            );

            $item->update([
                'current_version_id' => $version->id,
                'updated_by' => auth()->id(),
            ]);

            $this->audit->log('information.version.created', $item, [], ['version' => $version->version]);

            return $version;
        });
    }

    public function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;

        while (InformationItem::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function syncTags(InformationItem $item, array|string $tags): void
    {
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        $ids = collect($tags)->map(function ($name) {
            return \App\Models\Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => trim($name)]
            )->id;
        });

        $item->tags()->sync($ids);
    }
}
