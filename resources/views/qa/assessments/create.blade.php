@extends('layouts.app')

{{-- Form pembuatan penilaian kualitatif (PRD QA Assessment §4). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A --}}

@section('title', 'Buat Penilaian QA')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Buat Penilaian</h1>
        <p class="text-sm text-ink-500">Pilih agent dan periode, lalu isi item penilaian per kategori.</p>
    </div>

    @include('qa.assessment._nav')

    <form method="POST" action="{{ route('qa.assessments.store') }}" class="card card-body max-w-2xl space-y-5">
        @csrf

        <div>
            <label for="agent_id" class="form-label">Agent</label>
            <select id="agent_id" name="agent_id" class="form-input w-full" required>
                <option value="">Pilih agent</option>
                @foreach ($agents as $agent)
                    <option value="{{ $agent->id }}" @selected((int) old('agent_id') === $agent->id)>
                        {{ $agent->name }}{{ $agent->employee_id ? " ({$agent->employee_id})" : '' }}
                    </option>
                @endforeach
            </select>
            @error('agent_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-ink-500">Hanya agent yang ditugaskan kepada Anda (lihat menu Penugasan).</p>
        </div>

        <div>
            <label for="period_id" class="form-label">Periode</label>
            <select id="period_id" name="period_id" class="form-input w-full" required>
                <option value="">Pilih periode</option>
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}" @selected((int) old('period_id') === $period->id)>{{ $period->name }}</option>
                @endforeach
            </select>
            @error('period_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            @if ($periods->isEmpty())
                <p class="mt-1 text-xs text-amber-600">Belum ada periode aktif. Buat dulu di menu Periode.</p>
            @endif
        </div>

        <div>
            <label for="general_notes" class="form-label">Catatan umum</label>
            <textarea id="general_notes" name="general_notes" rows="4" class="form-input w-full"
                      placeholder="Ringkasan penilaian secara keseluruhan (opsional)">{{ old('general_notes') }}</textarea>
            @error('general_notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('qa.assessments.index') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Simpan</button>
        </div>
    </form>
@endsection
