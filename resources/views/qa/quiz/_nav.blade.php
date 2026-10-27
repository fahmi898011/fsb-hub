<nav class="mb-5 flex flex-wrap gap-2">
    @foreach ([['qa.quiz.dashboard', 'Dashboard'], ['qa.quiz.templates.index', 'Template'], ['qa.quiz.questions.index', 'Bank Soal'], ['qa.quiz.categories.index', 'Kategori'], ['qa.quiz.results', 'Hasil']] as [$route, $label])
        @if ($route !== 'qa.quiz.results' || auth()->user()->can('quiz_result.view'))
            <a href="{{ route($route) }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs($route === 'qa.quiz.dashboard' ? $route : str_replace('.index', '.*', $route)) || ($route === 'qa.quiz.results' && request()->routeIs('qa.quiz.results')) ? 'bg-brand-600 text-white' : 'bg-white text-ink-600 ring-1 ring-ink-200 hover:bg-ink-50' }}">{{ $label }}</a>
        @endif
    @endforeach
</nav>