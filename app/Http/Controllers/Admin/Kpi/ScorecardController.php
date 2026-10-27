<?php

namespace App\Http\Controllers\Admin\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Kpi;
use App\Models\Scorecard;
use App\Models\ScorecardVersion;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master Scorecard (Admin) - PRD Employee KPI/QA §5/§6.
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Controller ini mengatur SCORECARD dan pembuatan VERSI. Isi versi (kategori &
 * parameter) ditangani controller terpisah agar tiap berkas tetap kecil.
 */
class ScorecardController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Scorecard::class);

        $filters = $request->only(['search', 'status', 'channel', 'kpi']);

        $scorecards = Scorecard::query()
            ->with(['kpi', 'channel'])
            ->withCount('versions')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['channel'] ?? null, fn ($query, $id) => $query->where('channel_id', $id))
            ->when($filters['kpi'] ?? null, fn ($query, $id) => $query->where('kpi_id', $id))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('admin.kpi.scorecards.index', [
            'scorecards' => $scorecards,
            'filters' => $filters,
            'channels' => Channel::ordered()->get(),
            'kpis' => Kpi::ordered()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Scorecard::class);

        return view('admin.kpi.scorecards.create', [
            'scorecard' => new Scorecard(),
            'channels' => Channel::ordered()->get(),
            'kpis' => Kpi::ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Scorecard::class);

        $data = $this->validated($request);
        $data['created_by'] = Auth::id();

        $scorecard = Scorecard::create($data + ['slug' => $this->uniqueSlug($data['name'])]);

        $this->audit->log('kpi.scorecard.create', $scorecard, [], [
            'name' => $scorecard->name,
            'kpi_id' => $scorecard->kpi_id,
            'channel_id' => $scorecard->channel_id,
        ]);

        return redirect()
            ->route('admin.kpi.scorecards.show', $scorecard)
            ->with('success', "Scorecard {$scorecard->name} berhasil dibuat. Tambahkan versi pertamanya.");
    }

    public function show(Scorecard $scorecard): View
    {
        $this->authorize('view', $scorecard);

        $scorecard->load(['kpi.division', 'channel']);

        $versions = $scorecard->versions()->withCount('categories')->get();

        return view('admin.kpi.scorecards.show', compact('scorecard', 'versions'));
    }

    public function edit(Scorecard $scorecard): View
    {
        $this->authorize('update', $scorecard);

        return view('admin.kpi.scorecards.edit', [
            'scorecard' => $scorecard,
            'channels' => Channel::ordered()->get(),
            'kpis' => Kpi::ordered()->get(),
        ]);
    }

    public function update(Request $request, Scorecard $scorecard): RedirectResponse
    {
        $this->authorize('update', $scorecard);

        $data = $this->validated($request, $scorecard);
        $data['updated_by'] = Auth::id();
        $before = ['name' => $scorecard->name, 'status' => $scorecard->status];

        $scorecard->update($data + ['slug' => $this->uniqueSlug($data['name'], $scorecard)]);

        $this->audit->log('kpi.scorecard.update', $scorecard, $before, [
            'name' => $scorecard->name,
            'status' => $scorecard->status,
        ]);

        return redirect()
            ->route('admin.kpi.scorecards.show', $scorecard)
            ->with('success', "Scorecard {$scorecard->name} berhasil diperbarui.");
    }

    public function destroy(Scorecard $scorecard): RedirectResponse
    {
        $this->authorize('delete', $scorecard);

        $name = $scorecard->name;

        $scorecard->delete();

        $this->audit->log('kpi.scorecard.delete', null, ['name' => $name], []);

        return redirect()
            ->route('admin.kpi.scorecards.index')
            ->with('success', "Scorecard {$name} berhasil dihapus.");
    }

    /**
     * Buat VERSI baru (PRD §6). Bila `from_version_id` diisi, kategori &
     * parameter versi itu disalin sebagai titik awal - inilah cara aman
     * mengubah bobot: versi lama tetap utuh untuk histori, versi baru dipakai
     * assessment berikutnya.
     */
    public function storeVersion(Request $request, Scorecard $scorecard): RedirectResponse
    {
        $this->authorize('update', $scorecard);

        $data = $request->validate([
            'from_version_id' => ['nullable', 'integer', Rule::exists('scorecard_versions', 'id')],
            'effective_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $source = isset($data['from_version_id'])
            ? $scorecard->versions()->whereKey($data['from_version_id'])->first()
            : null;

        $nextVersion = (int) $scorecard->versions()->max('version') + 1;

        $version = DB::transaction(function () use ($scorecard, $source, $nextVersion, $data) {
            $version = $scorecard->versions()->create([
                'version' => $nextVersion,
                'status' => 'draft',
                'effective_from' => $data['effective_from'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            if ($source) {
                $this->cloneContent($source, $version);
            }

            return $version;
        });

        $this->audit->log('kpi.scorecard.version.create', $scorecard, [], [
            'version' => $version->version,
            'from_version' => $source?->version,
        ]);

        $note = $source ? " Isi disalin dari versi {$source->version}." : '';

        return redirect()
            ->route('admin.kpi.versions.show', $version)
            ->with('success', "Versi {$version->version} dibuat sebagai draft.{$note}");
    }

    private function cloneContent(ScorecardVersion $source, ScorecardVersion $target): void
    {
        foreach ($source->categories()->with('parameters')->get() as $category) {
            $newCategory = $target->categories()->create([
                'name' => $category->name,
                'description' => $category->description,
                'status' => $category->status,
                'sort_order' => $category->sort_order,
            ]);

            foreach ($category->parameters as $parameter) {
                $newCategory->parameters()->create($parameter->only([
                    'name', 'description', 'weight', 'evaluation_type', 'parameter_type',
                    'ztp_effect', 'answer_polarity', 'guideline', 'penalty_rule',
                    'is_required', 'status', 'sort_order',
                ]));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Scorecard $ignore = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('scorecards', 'name')->ignore($ignore?->getKey()),
            ],
            'kpi_id' => ['required', 'integer', Rule::exists('kpis', 'id')],
            'channel_id' => ['nullable', 'integer', Rule::exists('channels', 'id')],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive', 'archived'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'name.unique' => 'Nama scorecard sudah dipakai.',
            'kpi_id.exists' => 'KPI yang dipilih tidak ditemukan.',
            'channel_id.exists' => 'Channel yang dipilih tidak ditemukan.',
        ]);

        $data['sort_order'] ??= 0;

        return $data;
    }

    private function uniqueSlug(string $name, ?Scorecard $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Scorecard::where('slug', $slug)->whereKeyNot($ignore?->getKey() ?? 0)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
