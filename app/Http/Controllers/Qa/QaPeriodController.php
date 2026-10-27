<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Models\QaPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Controller periode QA (PRD QA Assessment §7).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, periode
 */
class QaPeriodController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', QaPeriod::class);

        $periods = QaPeriod::query()
            ->withCount('assessments')
            ->with('parent:id,name')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString();

        return view('qa.periods.index', compact('periods'));
    }

    public function create()
    {
        $this->authorize('create', QaPeriod::class);

        $parents = QaPeriod::orderByDesc('start_date')->get(['id', 'name']);

        return view('qa.periods.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', QaPeriod::class);

        $data = $this->validatePeriod($request);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $period = QaPeriod::create($data);

        return redirect()
            ->route('qa.periods.index')
            ->with('success', "Periode \"{$period->name}\" berhasil dibuat.");
    }

    public function show(QaPeriod $qaPeriod)
    {
        $this->authorize('view', $qaPeriod);

        $qaPeriod->load(['parent:id,name', 'children:id,name,parent_period_id']);
        $qaPeriod->loadCount('assessments');

        return view('qa.periods.show', compact('qaPeriod'));
    }

    public function edit(QaPeriod $qaPeriod)
    {
        $this->authorize('update', $qaPeriod);

        $parents = QaPeriod::whereKeyNot($qaPeriod->id)->orderByDesc('start_date')->get(['id', 'name']);

        return view('qa.periods.edit', compact('qaPeriod', 'parents'));
    }

    public function update(Request $request, QaPeriod $qaPeriod)
    {
        $this->authorize('update', $qaPeriod);

        $data = $this->validatePeriod($request, $qaPeriod);
        $data['updated_by'] = auth()->id();

        $qaPeriod->update($data);

        return redirect()
            ->route('qa.periods.index')
            ->with('success', 'Periode berhasil diperbarui.');
    }

    public function destroy(QaPeriod $qaPeriod)
    {
        $this->authorize('delete', $qaPeriod);

        if ($qaPeriod->assessments()->exists()) {
            return back()->with('error', 'Periode masih dipakai assessment, tidak bisa dihapus.');
        }

        $qaPeriod->delete();

        return redirect()
            ->route('qa.periods.index')
            ->with('success', 'Periode berhasil dihapus.');
    }

    protected function validatePeriod(Request $request, ?QaPeriod $period = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'parent_period_id' => ['nullable', 'exists:qa_periods,id', Rule::notIn(array_filter([$period?->id]))],
            'status' => 'nullable|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        // Slug unik diturunkan dari nama (route key periode).
        $slug = Str::slug($data['name']);
        $exists = QaPeriod::where('slug', $slug)
            ->when($period, fn ($q) => $q->whereKeyNot($period->id))
            ->exists();
        $data['slug'] = $exists ? $slug . '-' . Str::lower(Str::random(4)) : $slug;

        return $data;
    }
}
