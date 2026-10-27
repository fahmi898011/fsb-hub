@php
    $editing = isset($questionBank);
    $currentType = old('question_type', $editing ? $questionBank->question_type : 'essay');
    $oldOptions = old('options');
    $currentOptions = $oldOptions ?? ($editing && $questionBank->options ? $questionBank->options : []);
    if ($currentType !== 'multiple_choice') {
        $currentOptions = [];
    }
    if ($currentType === 'multiple_choice' && $currentOptions === []) {
        $currentOptions = [
            ['text' => '', 'is_correct' => true],
            ['text' => '', 'is_correct' => false],
        ];
    }
@endphp

<div class="space-y-4">
    @if (! empty($targetForm))
        <input type="hidden" name="form_id" value="{{ $targetForm->id }}">
        <p class="rounded-lg border border-brand-100 bg-brand-50 px-3 py-2 text-xs text-brand-800">
            Soal disimpan di bank soal, lalu langsung ditambahkan ke form
            <span class="font-semibold">{{ $targetForm->title }}</span>.
        </p>
    @endif

    <p class="text-xs text-ink-500">
        Bank soal dipakai bersama: soal yang disimpan di sini bisa dipakai di form ujian mana pun.
    </p>

    <div>
        <label class="form-label">Kategori</label>
        <select class="form-input w-full" name="category_id">
            <option value="">Tanpa kategori</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('category_id', $editing ? $questionBank->category_id : null) === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="form-label">Tipe soal</label>
        <div class="flex gap-4 mt-1">
            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input type="radio" name="question_type" value="essay" @checked($currentType === 'essay')> Essay
            </label>
            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input type="radio" name="question_type" value="multiple_choice" @checked($currentType === 'multiple_choice')> Pilihan Ganda
            </label>
        </div>
        @error('question_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Pertanyaan</label>
        <textarea class="form-input w-full" name="question" rows="3" required>{{ old('question', $editing ? $questionBank->question : '') }}</textarea>
        @error('question')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div id="options-block" class="space-y-3 {{ $currentType === 'multiple_choice' ? '' : 'hidden' }}">
        <label class="form-label">Opsi jawaban <span class="font-normal text-ink-500">(centang jawaban benar)</span></label>
        <div id="options-list" class="space-y-2">
            @foreach ($currentOptions as $i => $option)
                <div class="flex items-center gap-2 option-row">
                    <span class="w-6 text-sm font-semibold text-ink-500">{{ chr(65 + $i) }}</span>
                    <input type="text" name="options[{{ $i }}][text]" value="{{ $option['text'] ?? '' }}"
                           class="form-input flex-1" placeholder="Teks opsi {{ chr(65 + $i) }}" required>
                    <label class="flex items-center gap-1 text-xs text-ink-600">
                        <input type="checkbox" name="options[{{ $i }}][is_correct]" value="1"
                               @checked(!empty($option['is_correct']))> Benar
                    </label>
                    <button type="button" class="remove-option text-xs text-red-600 hover:underline">Hapus</button>
                </div>
            @endforeach
        </div>
        <button type="button" id="add-option" class="btn-secondary btn-sm">+ Tambah Opsi</button>
        @error('options')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label">Poin</label>
            <input class="form-input w-full" type="number" name="point" min="0"
                   value="{{ old('point', $editing ? $questionBank->point : 1) }}">
        </div>
        <div>
            <label class="form-label">Status</label>
            <select class="form-input w-full" name="status">
                <option value="active" @selected(old('status', $editing ? $questionBank->status : 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(old('status', $editing ? $questionBank->status : '') === 'inactive')>Nonaktif</option>
            </select>
        </div>
    </div>
</div>

<script>
    (function () {
        const typeInputs = document.querySelectorAll('input[name="question_type"]');
        const optionsBlock = document.getElementById('options-block');
        const optionsList = document.getElementById('options-list');
        const addBtn = document.getElementById('add-option');
        let count = optionsList.querySelectorAll('.option-row').length;

        function toggleType() {
            const type = document.querySelector('input[name="question_type"]:checked').value;
            const isMc = type === 'multiple_choice';
            optionsBlock.classList.toggle('hidden', !isMc);
            optionsList.querySelectorAll('input[type="text"]').forEach((el) => { el.required = isMc; });
        }

        function reindex() {
            const rows = optionsList.querySelectorAll('.option-row');
            rows.forEach((row, i) => {
                row.querySelector('span').textContent = String.fromCharCode(65 + i);
                row.querySelector('input[type="text"]').name = 'options[' + i + '][text]';
                row.querySelector('input[type="checkbox"]').name = 'options[' + i + '][is_correct]';
            });
            count = rows.length;
        }

        typeInputs.forEach((el) => el.addEventListener('change', toggleType));

        addBtn.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 option-row';
            row.innerHTML =
                '<span class="w-6 text-sm font-semibold text-ink-500"></span>' +
                '<input type="text" name="options[' + count + '][text]" class="form-input flex-1" placeholder="Teks opsi" required>' +
                '<label class="flex items-center gap-1 text-xs text-ink-600"><input type="checkbox" name="options[' + count + '][is_correct]" value="1"> Benar</label>' +
                '<button type="button" class="remove-option text-xs text-red-600 hover:underline">Hapus</button>';
            optionsList.appendChild(row);
            count++;
            reindex();
        });

        optionsList.addEventListener('click', function (e) {
            if (!e.target.classList.contains('remove-option')) return;
            if (optionsList.querySelectorAll('.option-row').length <= 2) {
                alert('Minimal 2 opsi.');
                return;
            }
            e.target.closest('.option-row').remove();
            reindex();
        });

        toggleType();
    })();
</script>
