@extends('layouts.app')

{{-- Daftar form ujian tertulis (PRD QA Assessment §5). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B --}}

@section('title', 'Form Ujian Tertulis')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Form Ujian Tertulis</h1>
            <p class="text-sm text-ink-500">Susun soal dari bank soal, lalu distribusikan ke agent.</p>
        </div>
        @can('create', App\Models\ExamForm::class)
            <a href="{{ route('qa.exam.forms.create') }}" class="btn-primary">Buat Form</a>
        @endcan
    </div>

    @include('qa.assessment._nav')

    <div class="mb-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="search" class="form-label">Cari</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-input w-64" placeholder="Judul form">
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="published" @selected(request('status') === 'published')>Terbit</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary">Terapkan</button>
        </form>
    </div>

    @if ($forms->isEmpty())
        <x-empty-state title="Belum ada form ujian"
                       description="Buat form pertama, tambahkan soal, lalu terbitkan." />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Judul</th>
                            <th class="px-5 py-3 font-semibold">Soal</th>
                            <th class="px-5 py-3 font-semibold">Durasi</th>
                            <th class="px-5 py-3 font-semibold">Lulus</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($forms as $form)
                            <tr class="hover:bg-ink-50">
                                <td class="px-5 py-3 font-medium text-ink-900">{{ $form->title }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $form->questions_count }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $form->duration_minutes ? $form->duration_minutes . ' mnt' : 'Tanpa batas' }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $form->passing_score !== null ? $form->passing_score . '%' : '—' }}</td>
                                <td class="px-5 py-3"><x-status-badge :status="$form->status" /></td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('qa.exam.forms.show', $form) }}" class="text-brand-700 hover:underline">Lihat</a>
                                    @can('update', $form)
                                        <a href="{{ route('qa.exam.forms.edit', $form) }}" class="ml-3 text-brand-700 hover:underline">Ubah</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $forms->links() }}</div>
        </div>
    @endif
@endsection
