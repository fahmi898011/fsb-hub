<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Models\QaAssessment;
use App\Models\QaAssessmentCategory;
use App\Models\QaAssessmentItem;
use App\Models\QaPeriod;
use App\Models\QaAgentAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Controller penilaian kualitatif QA (PRD QA Assessment §4).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A
 */
class QaAssessmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', QaAssessment::class);

        $user = auth()->user();

        $assessments = QaAssessment::query()
            ->with(['agent', 'qa', 'period'])
            ->when($user->hasRole('qa'), fn ($q) => $q->where('qa_id', $user->id))
            ->when($user->hasRole('employee'), fn ($q) => $q->where('agent_id', $user->id))
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('agent_name_snapshot', 'like', "%{$request->search}%")
                  ->orWhere('qa_name_snapshot', 'like', "%{$request->search}%");
            }))
            ->when($request->period_id, fn ($q) => $q->where('period_id', $request->period_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $periods = QaPeriod::orderByDesc('start_date')->get(['id', 'name']);

        return view('qa.assessments.index', compact('assessments', 'periods'));
    }

    public function create()
    {
        $this->authorize('create', QaAssessment::class);

        $periods = QaPeriod::active()->orderByDesc('start_date')->get(['id', 'name']);

        $assignedAgentIds = QaAgentAssignment::where('qa_id', auth()->id())
            ->where('active', true)
            ->pluck('agent_id');
        $agents = User::role('employee')
            ->whereIn('id', $assignedAgentIds)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);

        return view('qa.assessments.create', compact('periods', 'agents'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', QaAssessment::class);

        $data = $request->validate([
            'agent_id' => ['required', 'exists:users,id', Rule::unique('qa_assessments', 'agent_id')->where('qa_id', auth()->id())->where('period_id', $request->period_id)],
            'period_id' => 'required|exists:qa_periods,id',
            'general_notes' => 'nullable|string',
        ]);

        $agent = User::findOrFail($data['agent_id']);
        $period = QaPeriod::findOrFail($data['period_id']);
        $qa = auth()->user();

        $data['qa_id'] = $qa->id;
        $data['agent_name_snapshot'] = $agent->name;
        $data['qa_name_snapshot'] = $qa->name;
        $data['period_name_snapshot'] = $period->name;
        $data['created_by'] = $qa->id;
        $data['updated_by'] = $qa->id;

        $assessment = QaAssessment::create($data);

        return redirect()
            ->route('qa.assessments.show', $assessment)
            ->with('success', 'Assessment berhasil dibuat. Tambahkan item penilaian per kategori.');
    }

    public function show(QaAssessment $qaAssessment)
    {
        $this->authorize('view', $qaAssessment);

        $qaAssessment->load(['agent', 'qa', 'period', 'items.category', 'publishedBy']);

        // Kategori aktif dipakai untuk select "tambah item" (hanya saat draft).
        $categories = QaAssessmentCategory::active()->ordered()->get(['id', 'name', 'description']);

        return view('qa.assessments.show', compact('qaAssessment', 'categories'));
    }

    public function edit(QaAssessment $qaAssessment)
    {
        $this->authorize('update', $qaAssessment);

        $qaAssessment->load(['items.category']);

        $categories = QaAssessmentCategory::active()->ordered()->get(['id', 'name', 'description']);

        return view('qa.assessments.edit', compact('qaAssessment', 'categories'));
    }

    public function update(Request $request, QaAssessment $qaAssessment)
    {
        $this->authorize('update', $qaAssessment);

        if ($qaAssessment->status !== 'draft') {
            return back()->with('error', 'Hanya assessment draft yang bisa diedit.');
        }

        $data = $request->validate([
            'general_notes' => 'nullable|string',
        ]);
        $data['updated_by'] = auth()->id();

        $qaAssessment->update($data);

        return redirect()
            ->route('qa.assessments.show', $qaAssessment)
            ->with('success', 'Assessment berhasil diperbarui.');
    }

    public function destroy(QaAssessment $qaAssessment)
    {
        $this->authorize('delete', $qaAssessment);

        if ($qaAssessment->status !== 'draft') {
            return back()->with('error', 'Hanya assessment draft yang bisa dihapus.');
        }

        $qaAssessment->delete();

        return redirect()
            ->route('qa.assessments.index')
            ->with('success', 'Assessment berhasil dihapus.');
    }

    // ========== Items ==========

    public function itemStore(Request $request, QaAssessment $qaAssessment)
    {
        $this->authorize('update', $qaAssessment);

        if ($qaAssessment->status !== 'draft') {
            return back()->with('error', 'Hanya assessment draft yang bisa ditambah item.');
        }

        $data = $request->validate([
            'category_id' => ['required', 'exists:qa_assessment_categories,id',
                Rule::unique('qa_assessment_items', 'category_id')->where('assessment_id', $qaAssessment->id)],
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
        ]);

        $data['assessment_id'] = $qaAssessment->id;

        DB::transaction(function () use ($qaAssessment, $data) {
            $maxOrder = $qaAssessment->items()->max('sort_order') ?? 0;
            $data['sort_order'] = $maxOrder + 1;
            QaAssessmentItem::create($data);
        });

        return back()->with('success', 'Item penilaian berhasil ditambahkan.');
    }

    public function itemUpdate(Request $request, QaAssessment $qaAssessment, QaAssessmentItem $item)
    {
        $this->authorize('update', $qaAssessment);

        if ($qaAssessment->status !== 'draft') {
            return back()->with('error', 'Hanya assessment draft yang bisa diedit.');
        }

        $data = $request->validate([
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
        ]);

        $item->update($data);

        return back()->with('success', 'Item penilaian berhasil diperbarui.');
    }

    public function itemDestroy(QaAssessment $qaAssessment, QaAssessmentItem $item)
    {
        $this->authorize('update', $qaAssessment);

        if ($qaAssessment->status !== 'draft') {
            return back()->with('error', 'Hanya assessment draft yang bisa dihapus itemnya.');
        }

        $item->delete();

        return back()->with('success', 'Item penilaian berhasil dihapus.');
    }

    // ========== Publish ==========

    public function publish(QaAssessment $qaAssessment)
    {
        $this->authorize('publish', $qaAssessment);

        if ($qaAssessment->status !== 'draft') {
            return back()->with('error', 'Hanya assessment draft yang bisa diterbitkan.');
        }

        if ($qaAssessment->items->count() === 0) {
            return back()->with('error', 'Assessment harus memiliki minimal 1 item sebelum diterbitkan.');
        }

        $qaAssessment->update([
            'status' => 'published',
            'published_by' => auth()->id(),
            'published_at' => now(),
        ]);

        app(\App\Services\QaNotifier::class)->assessmentPublished($qaAssessment);

        return redirect()
            ->route('qa.assessments.show', $qaAssessment)
            ->with('success', 'Assessment berhasil diterbitkan.');
    }
}
