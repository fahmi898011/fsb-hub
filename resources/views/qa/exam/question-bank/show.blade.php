@extends('layouts.app')

{{-- Detail soal bank ujian (PRD QA Assessment §5). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B --}}

@section('title', 'Detail Soal Ujian')

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Detail Soal</h1>
            <p class="text-sm text-ink-500">
                {{ $questionBank->category?->name ?? 'Tanpa kategori' }} &middot;
                {{ $questionBank->type_label }} &middot; {{ $questionBank->point }} poin &middot;
                @if ($questionBank->forms->isNotEmpty())
                    Dipakai di {{ $questionBank->forms->count() }} form
                @else
                    Belum dipakai di form mana pun
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-badge :variant="$questionBank->status === 'active' ? 'green' : 'gray'">
                {{ $questionBank->status === 'active' ? 'Aktif' : 'Nonaktif' }}
            </x-badge>
            @can('update', $questionBank)
                <a href="{{ route('qa.exam.question-bank.edit', $questionBank) }}" class="btn-secondary btn-sm">Ubah</a>
            @endcan
            @can('delete', $questionBank)
                <form method="POST" action="{{ route('qa.exam.question-bank.destroy', $questionBank) }}"
                      onsubmit="return confirm('Hapus soal ini dari bank soal? Soal juga hilang dari form draft yang memakainya.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-secondary btn-sm text-red-600">Hapus</button>
                </form>
            @endcan
        </div>
    </div>

    @include('qa.assessment._nav')

    <div class="card card-body mb-5 space-y-4">
        <h2 class="text-sm font-semibold text-ink-900">Pertanyaan</h2>
        <p class="whitespace-pre-line text-sm text-ink-700">{{ $questionBank->question }}</p>

        @if ($questionBank->question_type === 'multiple_choice' && $questionBank->options)
            <div class="space-y-2">
                @foreach ($questionBank->options as $option)
                    <div class="flex items-center gap-2 text-sm">
                        <span class="w-6 font-semibold text-ink-500">{{ $option['label'] }}</span>
                        <span class="{{ !empty($option['is_correct']) ? 'font-medium text-green-700' : 'text-ink-700' }}">
                            {{ $option['text'] }}
                        </span>
                        @if (!empty($option['is_correct']))
                            <x-badge variant="green">Benar</x-badge>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-ink-500">Jawaban dinilai manual (essay).</p>
        @endif
    </div>

    <div class="card card-body mb-5">
        <h2 class="mb-3 text-sm font-semibold text-ink-900">Dipakai pada form</h2>
        @if ($questionBank->forms->isEmpty())
            <p class="text-sm text-ink-500">
                Soal ini masih menganggur di bank soal. Pakai lewat tombol
                &ldquo;Ambil Soal yang Sudah Ada&rdquo; di halaman form ujian.
            </p>
        @else
            <ul class="space-y-2 text-sm">
                @foreach ($questionBank->forms as $form)
                    <li class="flex flex-wrap items-center justify-between gap-2">
                        <a href="{{ route('qa.exam.forms.show', $form) }}" class="text-brand-700 hover:underline">
                            {{ $form->title }}
                        </a>
                        <span class="text-xs text-ink-500">
                            {{ $form->pivot->point }} poin &middot; urutan {{ $form->pivot->sort_order }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="flex justify-between">
        <a href="{{ route('qa.exam.question-bank.index') }}" class="btn-secondary">Kembali</a>
        <p class="text-xs text-ink-500">
            Dibuat {{ $questionBank->created_at->format('d M Y H:i') }}
            @if ($questionBank->createdBy) oleh {{ $questionBank->createdBy->name }} @endif
        </p>
    </div>
@endsection
