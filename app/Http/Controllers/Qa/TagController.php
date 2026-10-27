<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengelolaan Tag (QA).
 *
 * Sebelumnya tag hanya tercipta "sambil lalu" saat QA mengisi form informasi
 * (di-sync inline dari kolom teks), sehingga tidak ada cara melihat daftar,
 * mengganti nama, atau menghapus tag yang salah ketik. Halaman ini
 * melengkapinya.
 */
class TagController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $this->authorizePermission('tag.view');

        $filters = $request->only(['search']);

        $tags = Tag::query()
            ->withCount('information')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('qa.tags.index', compact('tags', 'filters'));
    }

    public function edit(Tag $tag): View
    {
        $this->authorizePermission('tag.update');

        $tag->loadCount('information');

        return view('qa.tags.edit', ['tag' => $tag]);
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorizePermission('tag.update');

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('tags', 'name')->ignore($tag->getKey()),
            ],
        ], [
            'name.unique' => 'Nama tag sudah dipakai.',
        ]);

        $before = $tag->name;

        $tag->update([
            'name' => $data['name'],
            // Slug ikut diperbarui agar URL pencarian tetap konsisten.
            'slug' => $this->uniqueSlug($data['name'], $tag),
        ]);

        $this->audit->log('tag.update', null, ['name' => $before], ['name' => $tag->name]);

        return redirect()
            ->route('qa.tags.index')
            ->with('success', "Tag berhasil diubah menjadi {$tag->name}.");
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorizePermission('tag.delete');

        $name = $tag->name;
        $usage = $tag->information()->count();

        // Lepaskan dulu dari informasi terkait, agar tidak menyisakan
        // baris yatim di tabel pivot.
        $tag->information()->detach();
        $tag->delete();

        $this->audit->log('tag.delete', null, ['name' => $name, 'dipakai' => $usage], []);

        $message = $usage > 0
            ? "Tag {$name} dihapus dan dilepas dari {$usage} informasi."
            : "Tag {$name} berhasil dihapus.";

        return redirect()
            ->route('qa.tags.index')
            ->with('success', $message);
    }

    /* ------------------------------------------------------------------ */
    /* Internal                                                            */
    /* ------------------------------------------------------------------ */

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    /**
     * Slug unik, mengabaikan tag yang sedang diperbarui.
     */
    private function uniqueSlug(string $name, Tag $ignore): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Tag::where('slug', $slug)->whereKeyNot($ignore->getKey())->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
