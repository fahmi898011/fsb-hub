@extends('layouts.app')

{{--
    Tulis soal bank ujian (PRD QA Assessment §5).

    Patokan halaman ini adalah BANYAK SOAL SEKALIGUS: satu halaman isian boleh
    berisi berapa pun soal (default tiga baris, bisa ditambah). Semua baris yang
    terisi disimpan dalam satu kali submit ke bank soal bersama, dan bila
    halaman dibuka dari sebuah form draft (`?form_id=`) soal-soal itu langsung
    ditempel ke form tersebut.

    AGENT: asisten | 2026-10-20 | satu halaman banyak soal | modul QA Assessment, bank soal
--}}

@section('title', 'Tambah Soal Ujian')

@section('content')
    @php
        // Setelah validasi gagal, tampilkan lagi baris yang sudah diisi QA.
        $oldRows = old('questions', []);
        $rowCount = max(3, is_array($oldRows) ? count($oldRows) : 0);
    @endphp

    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Tambah Soal</h1>
        <p class="text-sm text-ink-500">
            Satu halaman bisa berisi banyak soal sekaligus &mdash; semua baris yang diisi tersimpan bersama.
            Soal selalu masuk bank soal; pilih form ujian di bawah bila soal itu harus muncul saat peserta mengerjakan.
        </p>
    </div>

    @include('qa.assessment._nav')

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-medium">Ada isian yang perlu diperbaiki:</p>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('qa.exam.question-bank.store') }}"
          class="max-w-3xl space-y-4" data-question-composer>
        @csrf

        {{-- Soal hanya muncul di ujian peserta kalau menempel ke sebuah form.
             Karena itu form tujuannya dipilih di sini, bukan lewat URL rahasia
             `?form_id=` yang mudah terlewat.
             AGENT: asisten | 2026-10-20 | pilih form tujuan | modul QA Assessment --}}
        <div class="card card-body space-y-2">
            <label class="form-label" for="form_id">Masukkan ke form ujian</label>
            <select class="form-input w-full" name="form_id" id="form_id">
                <option value="">Hanya simpan ke bank soal (belum dipakai ujian)</option>
                @foreach ($forms as $form)
                    <option value="{{ $form->id }}" @selected((int) old('form_id', $targetForm?->id) === $form->id)>
                        {{ $form->title }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-ink-500">
                Soal tetap tersimpan di bank soal. Kalau form dipilih, soal langsung ditempel ke form itu
                dan baru terlihat oleh peserta setelah form diterbitkan.
            </p>
            @if ($forms->isEmpty())
                <p class="text-xs text-amber-700">
                    Belum ada form draft milik Anda.
                    <a href="{{ route('qa.exam.forms.create') }}" class="underline">Buat form ujian</a>
                    dulu bila soal ini harus dikerjakan peserta.
                </p>
            @endif
            @error('form_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="card card-body space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label">Kategori</label>
                    <select class="form-input w-full" name="category_id">
                        <option value="">Tanpa kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) old('category_id') === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select class="form-input w-full" name="status">
                        <option value="active" @selected(old('status', 'active') === 'active')>Aktif</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Nonaktif</option>
                    </select>
                    @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="text-xs text-ink-500">
                Kategori dan status di atas berlaku untuk semua soal di halaman ini.
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-ink-500">
                Tiga baris sudah disiapkan. Baris yang dikosongkan diabaikan saat disimpan.
            </p>
            <span class="text-xs text-ink-500">
                <span data-row-count>{{ $rowCount }}</span> baris siap diisi
            </span>
        </div>

        <div class="space-y-4" data-question-rows>
            @for ($i = 0; $i < $rowCount; $i++)
                @include('qa.exam.forms._question-row', ['index' => $i])
            @endfor
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <button type="button" class="btn-secondary btn-sm" data-add-row>+ Tambah soal</button>
            <div class="flex items-center gap-2">
                <a class="btn-secondary" href="{{ route('qa.exam.question-bank.index') }}">Batal</a>
                <button class="btn-primary">Simpan semua soal</button>
            </div>
        </div>
    </form>

    <template data-row-template>
        @include('qa.exam.forms._question-row', ['index' => '__INDEX__'])
    </template>

    @include('qa.exam.forms._composer-script')
@endsection

