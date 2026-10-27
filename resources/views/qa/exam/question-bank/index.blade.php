@extends('layouts.app')

{{-- Bank soal ujian tertulis (PRD QA Assessment §5). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B --}}

@section('title', 'Bank Soal Ujian')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Bank Soal Ujian</h1>
            <p class="text-sm text-ink-500">
                Bank soal dipakai bersama. Satu soal boleh dipakai di banyak form, dan satu form
                berisi banyak soal.
            </p>
        </div>
        @can('create', App\Models\QaExamQuestionBank::class)
            <a href="{{ route('qa.exam.question-bank.create', request('form_id') && request('form_id') !== 'none' ? ['form_id' => request('form_id')] : []) }}"
               class="btn-primary">Tambah Soal</a>
        @endcan
    </div>

    @include('qa.assessment._nav')

    <div class="mb-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="search" class="form-label">Cari</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-input w-64" placeholder="Teks soal">
            </div>
            <div>
                <label for="form_id" class="form-label">Dipakai di form</label>
                <select id="form_id" name="form_id" class="form-input">
                    <option value="">Semua soal</option>
                    <option value="none" @selected(request('form_id') === 'none')>Belum dipakai form</option>
                    @foreach ($forms as $form)
                        <option value="{{ $form->id }}" @selected((string) request('form_id') === (string) $form->id)>
                            {{ $form->title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="category_id" class="form-label">Kategori</label>
                <select id="category_id" name="category_id" class="form-input">
                    <option value="">Semua</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) request('category_id') === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="question_type" class="form-label">Tipe</label>
                <select id="question_type" name="question_type" class="form-input">
                    <option value="">Semua</option>
                    <option value="essay" @selected(request('question_type') === 'essay')>Essay</option>
                    <option value="multiple_choice" @selected(request('question_type') === 'multiple_choice')>Pilihan Ganda</option>
                </select>
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary">Terapkan</button>
        </form>
    </div>

    @if ($questions->isEmpty())
        <x-empty-state title="Belum ada soal"
                       description="Tambahkan soal pertama ke bank soal ujian tertulis." />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Soal</th>
                            <th class="px-5 py-3 font-semibold">Dipakai di</th>
                            <th class="px-5 py-3 font-semibold">Kategori</th>
                            <th class="px-5 py-3 font-semibold">Tipe</th>
                            <th class="px-5 py-3 font-semibold">Poin</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($questions as $question)
                            <tr class="hover:bg-ink-50">
                                <td class="max-w-md px-5 py-3 font-medium text-ink-900">{{ \Illuminate\Support\Str::limit($question->question, 90) }}</td>
                                <td class="px-5 py-3 text-ink-600">
                                    @forelse ($question->forms as $usedIn)
                                        <a href="{{ route('qa.exam.forms.show', $usedIn) }}"
                                           class="block text-brand-700 hover:underline">
                                            {{ \Illuminate\Support\Str::limit($usedIn->title, 40) }}
                                        </a>
                                    @empty
                                        <span class="text-ink-500">Belum dipakai</span>
                                    @endforelse
                                </td>
                                <td class="px-5 py-3 text-ink-600">{{ $question->category?->name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <x-badge :variant="$question->question_type === 'essay' ? 'amber' : 'brand'">
                                        {{ $question->type_label }}
                                    </x-badge>
                                </td>
                                <td class="px-5 py-3 text-ink-600">{{ $question->point }}</td>
                                <td class="px-5 py-3">
                                    <x-badge :variant="$question->status === 'active' ? 'green' : 'gray'">
                                        {{ $question->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                    </x-badge>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('qa.exam.question-bank.show', $question) }}" class="text-brand-700 hover:underline">Lihat</a>
                                    @can('update', $question)
                                        <a href="{{ route('qa.exam.question-bank.edit', $question) }}" class="ml-3 text-brand-700 hover:underline">Ubah</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $questions->links() }}</div>
        </div>
    @endif
@endsection
