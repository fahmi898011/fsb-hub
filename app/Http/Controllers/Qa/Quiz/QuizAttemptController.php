<?php

namespace App\Http\Controllers\Qa\Quiz;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\QuizTemplate;
use App\Services\AuditLogService;
use App\Services\QuizService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizAttemptController extends Controller
{
    public function __construct(
        private readonly QuizService $quizzes,
        private readonly AuditLogService $audit,
    ) {}

    public function dashboard(): View
    {
        $user = auth()->user();
        abort_unless($user->can('quiz.view'), 403);
        $attempts = QuizAttempt::query()
            ->when($user->hasRole('supervisor'), fn ($q) => $q->whereHas('employee.team', fn ($teams) => $teams->where('teams.leader_id', $user->id)));
        $total = (clone $attempts)->count();
        $completed = (clone $attempts)->completed();
        $teamDepartmentIds = $user->hasRole('supervisor')
            ? $user->ledTeams()->pluck('department_id')->filter()->unique()
            : collect();

        return view('qa.quiz.dashboard', [
            'stats' => [
                'templates' => QuizTemplate::query()->when($user->hasRole('supervisor'), fn ($q) => $q->where(fn ($scope) => $scope->whereNull('department_id')->orWhereIn('department_id', $teamDepartmentIds)))->count(),
                'active' => QuizTemplate::query()->where('status', 'active')->when($user->hasRole('supervisor'), fn ($q) => $q->where(fn ($scope) => $scope->whereNull('department_id')->orWhereIn('department_id', $teamDepartmentIds)))->count(),
                'attempts' => $total,
                'average' => (clone $completed)->avg('score'),
            ],
            'recent' => (clone $attempts)->with('employee:id,name')->latest('id')->limit(10)->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('quiz.take') || $user->can('quiz_result.view'), 403);
        $templates = QuizTemplate::query()
            ->when(! $user->can('quiz.take'), fn ($q) => $q->whereRaw('1 = 0'))
            ->active()
            ->with(['versions' => fn ($q) => $q->active()->orderByDesc('version')])
            ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $user->department_id))
            ->orderBy('name')->get()
            ->filter(fn ($template) => $template->versions->isNotEmpty());

        $attempts = QuizAttempt::query()->where('employee_id', $user->id)
            ->with('template:id,name,slug')->latest('id')->paginate(15);

        return view('qa.quiz.employee.index', compact('templates', 'attempts'));
    }

    public function start(Request $request, QuizTemplate $template): RedirectResponse
    {
        $this->authorize('create', QuizAttempt::class);
        abort_unless($request->user()->can('quiz.take'), 403);
        abort_unless($template->status === 'active' && ($template->department_id === null || (int) $template->department_id === (int) $request->user()->department_id), 404);
        $version = $template->activeVersion();
        abort_unless($version && (! $version->effective_from || $version->effective_from->startOfDay()->lte(now()))
            && (! $version->effective_to || $version->effective_to->endOfDay()->gte(now())), 404);

        $attempt = $this->quizzes->startAttempt($version, $request->user());
        $this->authorize('take', $attempt);

        return redirect()->route('quiz.attempts.take', $attempt);
    }

    public function take(QuizAttempt $attempt): View|RedirectResponse
    {
        $this->authorize('take', $attempt);
        if ($this->isExpired($attempt)) {
            $this->quizzes->submit($attempt, []);
            return redirect()->route('quiz.attempts.show', $attempt)->with('warning', 'Waktu pengerjaan habis; jawaban yang tersimpan telah dikumpulkan.');
        }

        $attempt->load('version.questions');
        abort_unless($attempt->version, 404);

        return view('qa.quiz.employee.take', compact('attempt'));
    }

    public function submit(Request $request, QuizAttempt $attempt): RedirectResponse
    {
        $this->authorize('take', $attempt);
        if ($this->isExpired($attempt)) {
            $request->merge(['answers' => []]);
        }

        $validated = $request->validate(['answers' => ['nullable', 'array']]);
        $questions = $attempt->version?->questions()->get() ?? collect();
        $selected = [];
        $allowedQuestionIds = $questions->modelKeys();
        foreach ($validated['answers'] ?? [] as $questionId => $optionIds) {
            abort_unless(in_array((int) $questionId, $allowedQuestionIds, true) && is_array($optionIds), 422);
            $question = $questions->firstWhere('id', (int) $questionId);
            $allowedOptionIds = collect($question->options_snapshot ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
            $optionIds = array_values(array_unique(array_map('intval', $optionIds)));
            abort_if(array_diff($optionIds, $allowedOptionIds), 422, 'Pilihan jawaban tidak valid.');
            $selected[(int) $questionId] = $optionIds;
        }

        $result = $this->quizzes->submit($attempt, $selected);
        $this->audit->log('quiz.attempt.submit', $result, [], ['score' => $result->score, 'result' => $result->result]);

        return redirect()->route('quiz.attempts.show', $result)->with('success', 'Jawaban quiz berhasil dikumpulkan.');
    }

    public function show(QuizAttempt $attempt): View
    {
        $this->authorize('view', $attempt);
        $attempt->load(['template:id,name,slug', 'employee:id,name', 'answers.versionQuestion']);

        return view('qa.quiz.employee.result', compact('attempt'));
    }

    public function report(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'result' => ['nullable', 'in:pass,fail,pending'],
        ]);
        $user = $request->user();
        $attempts = QuizAttempt::query()->with(['employee:id,name', 'template:id,name'])
            // Supervisor quiz permissions are intended for coaching their own
            // team, not for browsing results from every department/team.
            ->when($user->hasRole('supervisor'), fn ($q) => $q->whereHas('employee.team', fn ($teams) => $teams->where('teams.leader_id', $user->id)))
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->whereHas('employee', fn ($e) => $e->where('name', 'like', "%{$value}%")))
            ->when($filters['result'] ?? null, fn ($q, $value) => $q->where('result', $value))
            ->latest('id')->paginate(25)->withQueryString();

        return view('qa.quiz.results', compact('attempts', 'filters'));
    }

    private function isExpired(QuizAttempt $attempt): bool
    {
        $attempt->loadMissing('version');
        $limit = (int) ($attempt->version?->time_limit ?? 0);

        return $limit > 0 && $attempt->started_at?->copy()->addMinutes($limit)->isPast();
    }
}