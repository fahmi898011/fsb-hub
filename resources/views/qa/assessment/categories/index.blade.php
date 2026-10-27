@extends('layouts.app')

{{-- Master kategori penilaian kualitatif (PRD QA Assessment §4). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A --}}

@section('title', 'Kategori Penilaian')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Kategori Penilaian</h1>
            <p class="text-sm text-ink-500">Kelompokkan penilaian: Greeting, Handling Customer, Tes Call, dll.</p>
        </div>
        @can('create', App\Models\QaAssessmentCategory::class)
            <a href="{{ route('qa.assessment.categories.create') }}" class="btn-primary">Tambah Kategori</a>
        @endcan
    </div>

    @include('qa.assessment._nav')

    <div class="mb-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="search" class="form-label">Cari</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-input" placeholder="Nama kategori">
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

    @if ($categories->isEmpty())
        <x-empty-state title="Belum ada kategori"
                       description="Buat kategori pertama untuk dipakai saat menulis penilaian." />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Nama</th>
                            <th class="px-5 py-3 font-semibold">Deskripsi</th>
                            <th class="px-5 py-3 font-semibold">Urutan</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($categories as $category)
                            <tr class="hover:bg-ink-50">
                                <td class="px-5 py-3 font-medium text-ink-900">{{ $category->name }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ \Illuminate\Support\Str::limit($category->description, 60) ?: '—' }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $category->sort_order }}</td>
                                <td class="px-5 py-3">
                                    <x-badge :variant="$category->status === 'active' ? 'green' : 'gray'">
                                        {{ $category->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                    </x-badge>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('qa.assessment.categories.show', $category) }}" class="text-brand-700 hover:underline">Lihat</a>
                                    @can('update', $category)
                                        <a href="{{ route('qa.assessment.categories.edit', $category) }}" class="ml-3 text-brand-700 hover:underline">Ubah</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $categories->links() }}</div>
        </div>
    @endif
@endsection
