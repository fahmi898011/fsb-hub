{{-- Navigasi modul QA Assessment (PRD QA Assessment §3-§7). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment --}}

@php
    $qaNav = [
        ['qa.assessments.index', 'Penilaian', App\Models\QaAssessment::class],
        ['qa.assessment.categories.index', 'Kategori', App\Models\QaAssessmentCategory::class],
        ['qa.exam.question-bank.index', 'Bank Soal', App\Models\QaExamQuestionBank::class],
        ['qa.exam.forms.index', 'Ujian', App\Models\ExamForm::class],
        ['qa.exam.grading.index', 'Penilaian Ujian', App\Models\ExamAssignment::class],
        ['qa.periods.index', 'Periode', App\Models\QaPeriod::class],
        ['qa.agent-assignments.index', 'Penugasan', App\Models\QaAgentAssignment::class],
    ];
@endphp

<nav class="mb-5 flex flex-wrap gap-2">
    @foreach ($qaNav as [$route, $label, $model])
        @can('viewAny', $model)
            <a href="{{ route($route) }}"
               class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs(str_replace('.index', '.*', $route)) ? 'bg-brand-600 text-white' : 'bg-white text-ink-600 ring-1 ring-ink-200 hover:bg-ink-50' }}">{{ $label }}</a>
        @endcan
    @endforeach
</nav>
