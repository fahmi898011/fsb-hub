{{-- Filter laporan QA Sampling (PRD Employee KPI/QA §42). Dipakai bersama
     halaman laporan agar definisi filter tidak berbeda antar halaman.
     AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting
     Variabel opsional: $showScore (tampilkan rentang skor) — default true. --}}
@php($showScore = $showScore ?? true)

{{-- Penjelasan cakupan. Tanpa ini, supervisor yang hanya melihat timnya akan
     mengira angkanya salah (terlalu kecil) atau mengira datanya hilang. --}}
@if (! empty($scopeNotice ?? null))
    <div class="mb-4 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900">
        <span class="font-semibold">Cakupan data:</span> {{ $scopeNotice }}
    </div>
@endif

<form method="GET" class="card card-body mb-5">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="from" class="form-label">Dari Tanggal</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="form-input">
        </div>
        <div>
            <label for="to" class="form-label">Sampai Tanggal</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="form-input">
        </div>
        <div>
            <label for="period" class="form-label">Periode</label>
            <select id="period" name="period" class="form-input">
                <option value="">Semua periode</option>
                @foreach ($options['periods'] as $period)
                    <option value="{{ $period }}" @selected(($filters['period'] ?? '') === $period)>{{ $period }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="department_id" class="form-label">Divisi</label>
            <select id="department_id" name="department_id" class="form-input">
                <option value="">Semua divisi</option>
                @foreach ($options['divisions'] as $division)
                    <option value="{{ $division->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $division->id)>{{ $division->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="scorecard_id" class="form-label">Scorecard</label>
            <select id="scorecard_id" name="scorecard_id" class="form-input">
                <option value="">Semua scorecard</option>
                @foreach ($options['scorecards'] as $scorecard)
                    <option value="{{ $scorecard->id }}" @selected((string) ($filters['scorecard_id'] ?? '') === (string) $scorecard->id)>{{ $scorecard->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="channel_id" class="form-label">Channel</label>
            <select id="channel_id" name="channel_id" class="form-input">
                <option value="">Semua channel</option>
                @foreach ($options['channels'] as $channel)
                    <option value="{{ $channel->id }}" @selected((string) ($filters['channel_id'] ?? '') === (string) $channel->id)>{{ $channel->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="qa_id" class="form-label">QA</label>
            <select id="qa_id" name="qa_id" class="form-input">
                <option value="">Semua QA</option>
                @foreach ($options['qas'] as $qa)
                    <option value="{{ $qa->id }}" @selected((string) ($filters['qa_id'] ?? '') === (string) $qa->id)>{{ $qa->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-input">
                <option value="">Semua status</option>
                @foreach (['draft' => 'Draft', 'submitted' => 'Terkirim', 'reviewed' => 'Diperiksa', 'locked' => 'Terkunci', 'cancelled' => 'Dibatalkan'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($showScore)
            <div>
                <label for="score_min" class="form-label">Skor Minimal</label>
                <input id="score_min" name="score_min" type="number" step="0.01" min="0" max="100"
                       value="{{ $filters['score_min'] ?? '' }}" class="form-input" placeholder="mis. 0">
            </div>
            <div>
                <label for="score_max" class="form-label">Skor Maksimal</label>
                <input id="score_max" name="score_max" type="number" step="0.01" min="0" max="100"
                       value="{{ $filters['score_max'] ?? '' }}" class="form-input" placeholder="mis. 70">
            </div>
        @endif
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <button type="submit" class="btn-primary">Terapkan</button>
        <a href="{{ url()->current() }}" class="btn-secondary">Reset</a>
        <span class="text-xs text-ink-400">
            Rentang bawaan 30 hari terakhir bila tanggal dikosongkan.
        </span>
    </div>
</form>
