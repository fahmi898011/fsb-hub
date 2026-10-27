<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Models\QaAgentAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controller penugasan QA ke agent (PRD QA Assessment §6).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian C
 *
 * Penetapan OLEH ADMIN (PRD §6) agar tidak ada QA mengklaim agent sendiri.
 * Policy `qa.agent-assignment.*` sudah membatasi di sisi otorisasi.
 */
class QaAgentAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', QaAgentAssignment::class);

        $user = auth()->user();

        $assignments = QaAgentAssignment::query()
            ->with(['qa:id,name', 'agent:id,name', 'assignedBy:id,name'])
            ->when($user->hasRole('qa'), fn ($q) => $q->where('qa_id', $user->id))
            ->when($user->hasRole('employee'), fn ($q) => $q->where('agent_id', $user->id))
            ->when($request->qa_id, fn ($q) => $q->where('qa_id', $request->qa_id))
            ->when($request->agent_id, fn ($q) => $q->where('agent_id', $request->agent_id))
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $qas = User::role('qa')->orderBy('name')->get(['id', 'name']);
        $agents = User::role('employee')->orderBy('name')->get(['id', 'name']);

        return view('qa.agent-assignments.index', compact('assignments', 'qas', 'agents'));
    }

    public function create()
    {
        $this->authorize('create', QaAgentAssignment::class);

        $qas = User::role('qa')->orderBy('name')->get(['id', 'name']);
        $agents = User::role('employee')->orderBy('name')->get(['id', 'name']);

        return view('qa.agent-assignments.create', compact('qas', 'agents'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', QaAgentAssignment::class);

        $data = $request->validate([
            'qa_id' => ['required', 'exists:users,id',
                Rule::unique('qa_agent_assignments', 'qa_id')->where('agent_id', $request->agent_id)],
            'agent_id' => 'required|exists:users,id',
            'active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $data['assigned_by'] = auth()->id();
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        QaAgentAssignment::create($data);

        return redirect()
            ->route('qa.agent-assignments.index')
            ->with('success', 'Penugasan QA ke agent berhasil dibuat.');
    }

    public function show(QaAgentAssignment $qaAgentAssignment)
    {
        $this->authorize('view', $qaAgentAssignment);

        $qaAgentAssignment->load(['qa', 'agent', 'assignedBy', 'createdBy', 'updatedBy']);

        return view('qa.agent-assignments.show', compact('qaAgentAssignment'));
    }

    public function edit(QaAgentAssignment $qaAgentAssignment)
    {
        $this->authorize('update', $qaAgentAssignment);

        $qas = User::role('qa')->orderBy('name')->get(['id', 'name']);
        $agents = User::role('employee')->orderBy('name')->get(['id', 'name']);

        return view('qa.agent-assignments.edit', compact('qaAgentAssignment', 'qas', 'agents'));
    }

    public function update(Request $request, QaAgentAssignment $qaAgentAssignment)
    {
        $this->authorize('update', $qaAgentAssignment);

        $data = $request->validate([
            'active' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        $data['updated_by'] = auth()->id();

        $qaAgentAssignment->update($data);

        return redirect()
            ->route('qa.agent-assignments.index')
            ->with('success', 'Penugasan berhasil diperbarui.');
    }

    public function destroy(QaAgentAssignment $qaAgentAssignment)
    {
        $this->authorize('delete', $qaAgentAssignment);

        $qaAgentAssignment->delete();

        return redirect()
            ->route('qa.agent-assignments.index')
            ->with('success', 'Penugasan berhasil dihapus.');
    }
}
