<div class="space-y-4">
    <div>
        <label class="form-label">Judul form</label>
        <input class="form-input w-full" name="title" required maxlength="255"
               value="{{ old('title', $examForm->title ?? '') }}">
        @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Deskripsi / petunjuk</label>
        <textarea class="form-input w-full" name="description" rows="3">{{ old('description', $examForm->description ?? '') }}</textarea>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label">Batas waktu (menit)</label>
            <input class="form-input w-full" type="number" name="duration_minutes" min="1"
                   value="{{ old('duration_minutes', $examForm->duration_minutes ?? '') }}" placeholder="Kosongkan = tanpa batas">
            @error('duration_minutes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Nilai lulus (%)</label>
            <input class="form-input w-full" type="number" name="passing_score" min="0" max="100" step="0.01"
                   value="{{ old('passing_score', $examForm->passing_score ?? '') }}" placeholder="Kosongkan = tanpa ambang">
            @error('passing_score')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label">Metode pengambilan nilai (retake)</label>
            <select class="form-input w-full" name="grading_method">
                <option value="highest" @selected(old('grading_method', $examForm->grading_method ?? 'highest') === 'highest')>Nilai tertinggi</option>
                <option value="latest" @selected(old('grading_method', $examForm->grading_method ?? '') === 'latest')>Nilai terakhir</option>
                <option value="average" @selected(old('grading_method', $examForm->grading_method ?? '') === 'average')>Rata-rata</option>
            </select>
        </div>
        <div>
            <label class="form-label">Batas percobaan</label>
            <input class="form-input w-full" type="number" name="max_attempts" min="1"
                   value="{{ old('max_attempts', $examForm->max_attempts ?? '') }}" placeholder="Kosongkan = tanpa batas">
            @error('max_attempts')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm text-ink-700">
        <input type="hidden" name="allow_retake" value="0">
        <input type="checkbox" name="allow_retake" value="1" @checked(old('allow_retake', $examForm->allow_retake ?? false))>
        Izinkan percobaan ulang
    </label>
</div>
