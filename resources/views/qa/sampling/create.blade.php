@extends('layouts.app')

{{-- Form membuat assessment QA Sampling (PRD §14). --}}
{{-- AGENT: asisten | 2026-10-03 | buat berkas baru | modul KPI, fase 2 QA sampling --}}

@section('title', 'Ambil Sample Baru')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Ambil Sample Baru</h1>
        <p class="text-sm text-ink-500">
            Pilih karyawan dan versi scorecard yang aktif, lalu tempel tautan sample. Penilaian dilakukan di halaman berikutnya.
        </p>
    </div>

    <div class="card card-body max-w-3xl">
        <form method="POST" action="{{ route('qa.sampling.store') }}">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="employee_id" class="form-label">Karyawan yang Dinilai</label>
                    <select id="employee_id" name="employee_id" class="form-input" required>
                        <option value="">— pilih karyawan —</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int) old('employee_id') === $employee->id)>
                                {{ $employee->name }}@if ($employee->departmentName()) — {{ $employee->departmentName() }}@endif
                            </option>
                        @endforeach
                    </select>
                    @error('employee_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="scorecard_version_id" class="form-label">Scorecard (Versi Aktif)</label>
                    <select id="scorecard_version_id" name="scorecard_version_id" class="form-input" required>
                        <option value="">— pilih scorecard —</option>
                        @foreach ($versions as $version)
                            <option value="{{ $version->id }}" @selected((int) old('scorecard_version_id') === $version->id)>
                                {{ $version->scorecard?->name }} · v{{ $version->version }}@if ($version->scorecard?->channel) ({{ $version->scorecard->channel->name }})@endif
                            </option>
                        @endforeach
                    </select>
                    @error('scorecard_version_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    @if ($versions->isEmpty())
                        <p class="mt-1 text-xs text-amber-600">
                            Belum ada versi scorecard yang aktif. Minta Admin mengaktifkan versi terlebih dahulu.
                        </p>
                    @endif
                </div>

                <div>
                    <label for="sample_link" class="form-label">Tautan Sample</label>
                    <input id="sample_link" name="sample_link" type="text" value="{{ old('sample_link') }}"
                           class="form-input" placeholder="https://...">
                    @error('sample_link')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="sample_id" class="form-label">ID Sample <span class="text-ink-400">(opsional)</span></label>
                    <input id="sample_id" name="sample_id" type="text" value="{{ old('sample_id') }}"
                           class="form-input"
                           placeholder="Nomor tiket / interaction ID">
                    @error('sample_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-ink-400">Dipakai untuk mencegah sample dinilai dua kali.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="assessment_date" class="form-label">Tanggal Penilaian</label>
                        <input id="assessment_date" name="assessment_date" type="date"
                               value="{{ old('assessment_date', now()->toDateString()) }}"
                               class="form-input" required>
                        @error('assessment_date')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="period" class="form-label">Periode <span class="text-ink-400">(opsional)</span></label>
                        <input id="period" name="period" type="text" value="{{ old('period') }}"
                               class="form-input"
                               placeholder="mis. 2026-W40">
                        @error('period')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="notes" class="form-label">Catatan <span class="text-ink-400">(opsional)</span></label>
                    <textarea id="notes" name="notes" rows="3" class="form-input">{{ old('notes') }}</textarea>
                    @error('notes')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Buat & Nilai</button>
                <a href="{{ route('qa.sampling.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
