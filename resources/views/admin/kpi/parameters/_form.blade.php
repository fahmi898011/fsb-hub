{{-- Form Parameter: dipakai halaman tambah & edit (PRD §8/§13). --}}
{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}
@php($p = $parameter ?? new App\Models\ScorecardParameter())

<div class="space-y-5">
    <div>
        <label for="name" class="form-label">Nama Parameter <span class="text-red-500">*</span></label>
        <input id="name" name="name" type="text" value="{{ old('name', $p->name ?? '') }}" required class="form-input" placeholder="mis. Empati">
        @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="weight" class="form-label">Bobot (%) <span class="text-red-500">*</span></label>
            <input id="weight" name="weight" type="number" step="0.01" min="0" max="100"
                   value="{{ old('weight', $p->weight ?? 0) }}" required class="form-input">
            <p class="mt-1 text-xs text-ink-400">Penalty = bobot ini saat parameter kena (lihat arah jawaban).</p>
            @error('weight')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="evaluation_type" class="form-label">Pilihan Jawaban <span class="text-red-500">*</span></label>
            <select id="evaluation_type" name="evaluation_type" required class="form-input">
                <option value="yes_no" @selected(old('evaluation_type', $p->evaluation_type ?? 'yes_no') === 'yes_no')>YES / NO</option>
                <option value="yes_no_na" @selected(old('evaluation_type', $p->evaluation_type ?? '') === 'yes_no_na')>YES / NO / NA</option>
            </select>
            @error('evaluation_type')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label for="parameter_type" class="form-label">Tipe Parameter <span class="text-red-500">*</span></label>
            <select id="parameter_type" name="parameter_type" required class="form-input">
                @foreach (['normal' => 'Normal', 'critical' => 'Critical', 'zero_tolerance' => 'Zero Tolerance'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('parameter_type', $p->parameter_type ?? 'normal') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('parameter_type')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="ztp_effect" class="form-label">Efek ZTP</label>
            <select id="ztp_effect" name="ztp_effect" class="form-input">
                <option value="zero_score" @selected(old('ztp_effect', $p->ztp_effect ?? 'zero_score') === 'zero_score')>Skor jadi 0</option>
                <option value="weight_only" @selected(old('ztp_effect', $p->ztp_effect ?? '') === 'weight_only')>Penalty bobot saja</option>
            </select>
            <p class="mt-1 text-xs text-ink-400">Hanya dipakai bila tipe = Zero Tolerance.</p>
        </div>
        <div>
            <label for="answer_polarity" class="form-label">Arah Jawaban</label>
            <select id="answer_polarity" name="answer_polarity" class="form-input">
                <option value="normal" @selected(old('answer_polarity', $p->answer_polarity ?? 'normal') === 'normal')>Normal (NO = penalty)</option>
                <option value="inverted" @selected(old('answer_polarity', $p->answer_polarity ?? '') === 'inverted')>Terbalik (YES = penalty)</option>
            </select>
            <p class="mt-1 text-xs text-ink-400">ZTP biasanya terbalik: "terjadi pelanggaran?"</p>
        </div>
    </div>

    <div>
        <label for="guideline" class="form-label">Pedoman Penilaian</label>
        <textarea id="guideline" name="guideline" rows="4" class="form-input" placeholder="Penjelasan bagaimana parameter ini dinilai.">{{ old('guideline', $p->guideline ?? '') }}</textarea>
        @error('guideline')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="penalty_rule" class="form-label">Aturan Penalty (opsional)</label>
        <textarea id="penalty_rule" name="penalty_rule" rows="2" class="form-input">{{ old('penalty_rule', $p->penalty_rule ?? '') }}</textarea>
        @error('penalty_rule')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label for="sort_order" class="form-label">Urutan</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $p->sort_order ?? 0) }}" class="form-input">
        </div>
        <div>
            <label for="status" class="form-label">Status <span class="text-red-500">*</span></label>
            <select id="status" name="status" required class="form-input">
                <option value="active" @selected(old('status', $p->status ?? 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(old('status', $p->status ?? '') === 'inactive')>Nonaktif</option>
            </select>
        </div>
        <div class="flex items-end">
            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" name="is_required" value="1" @checked(old('is_required', $p->is_required ?? true)) class="rounded border-ink-300">
                Wajib dinilai
            </label>
        </div>
    </div>
</div>
