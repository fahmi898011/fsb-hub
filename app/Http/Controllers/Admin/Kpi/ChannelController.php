<?php

namespace App\Http\Controllers\Admin\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master Channel KPI (Admin) - PRD Employee KPI/QA §4.
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Namespace `Admin\Kpi` (bukan sekadar `Admin`) karena aplikasi sudah punya
 * `Admin\ChannelController`? Tidak - tetapi ada istilah "channel" lain di modul
 * Chat (`conversations`). Memisahkan di bawah `Admin\Kpi` menjaga batas modul
 * tetap jelas dan mencegah salah tarik.
 */
class ChannelController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Channel::class);

        $filters = $request->only(['search', 'status']);

        $channels = Channel::query()
            ->withCount('scorecards')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('admin.kpi.channels.index', compact('channels', 'filters'));
    }

    public function create(): View
    {
        $this->authorize('create', Channel::class);

        return view('admin.kpi.channels.create', ['channel' => new Channel()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Channel::class);

        $data = $this->validated($request);

        $channel = Channel::create($data + ['slug' => $this->uniqueSlug($data['name'])]);

        $this->audit->log('kpi.channel.create', $channel, [], [
            'name' => $channel->name,
            'status' => $channel->status,
        ]);

        return redirect()
            ->route('admin.kpi.channels.index')
            ->with('success', "Channel {$channel->name} berhasil ditambahkan.");
    }

    public function edit(Channel $channel): View
    {
        $this->authorize('update', $channel);

        $channel->loadCount('scorecards');

        return view('admin.kpi.channels.edit', compact('channel'));
    }

    public function update(Request $request, Channel $channel): RedirectResponse
    {
        $this->authorize('update', $channel);

        $data = $this->validated($request, $channel);
        $before = ['name' => $channel->name, 'status' => $channel->status];

        $channel->update($data + ['slug' => $this->uniqueSlug($data['name'], $channel)]);

        $this->audit->log('kpi.channel.update', $channel, $before, [
            'name' => $channel->name,
            'status' => $channel->status,
        ]);

        return redirect()
            ->route('admin.kpi.channels.index')
            ->with('success', "Channel {$channel->name} berhasil diperbarui.");
    }

    public function destroy(Channel $channel): RedirectResponse
    {
        $this->authorize('delete', $channel);

        $name = $channel->name;

        $channel->delete();

        $this->audit->log('kpi.channel.delete', null, ['name' => $name], []);

        return redirect()
            ->route('admin.kpi.channels.index')
            ->with('success', "Channel {$name} berhasil dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Channel $ignore = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('channels', 'name')->ignore($ignore?->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'name.unique' => 'Nama channel sudah dipakai.',
        ]);

        $data['sort_order'] ??= 0;

        return $data;
    }

    private function uniqueSlug(string $name, ?Channel $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Channel::where('slug', $slug)->whereKeyNot($ignore?->getKey() ?? 0)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
