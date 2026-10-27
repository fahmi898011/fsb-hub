<div class="space-y-4">
    @isset($qaAgentAssignment)
        {{-- Saat edit: pasangan QA/agent dikunci (unique pair), hanya status & catatan yang boleh berubah). --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">QA</label>
                <input class="form-input w-full bg-ink-50" value="{{ $qaAgentAssignment->qa?->name }}" disabled>
            </div>
            <div>
                <label class="form-label">Agent</label>
                <input class="form-input w-full bg-ink-50" value="{{ $qaAgentAssignment->agent?->name }}" disabled>
            </div>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">QA (penilai)</label>
                <select class="form-input w-full" name="qa_id" required>
                    <option value="">Pilih QA</option>
                    @foreach ($qas as $qa)
                        <option value="{{ $qa->id }}" @selected((int) old('qa_id') === $qa->id)>{{ $qa->name }}</option>
                    @endforeach
                </select>
                @error('qa_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label">Agent (dinilai)</label>
                <select class="form-input w-full" name="agent_id" required>
                    <option value="">Pilih agent</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}" @selected((int) old('agent_id') === $agent->id)>{{ $agent->name }}</option>
                    @endforeach
                </select>
                @error('agent_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    @endisset

    <div>
        <label class="form-label">Status</label>
        <select class="form-input w-full" name="active">
            <option value="1" @selected((string) old('active', $qaAgentAssignment->active ?? true) === '1')>Aktif</option>
            <option value="0" @selected((string) old('active', $qaAgentAssignment->active ?? '') === '0')>Nonaktif</option>
        </select>
    </div>

    <div>
        <label class="form-label">Catatan</label>
        <textarea class="form-input w-full" name="notes" rows="3"
                  placeholder="mis. Handling customer, penilaian tes call">{{ old('notes', $qaAgentAssignment->notes ?? '') }}</textarea>
    </div>
</div>
