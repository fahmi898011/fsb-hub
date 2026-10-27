{{--
    Satu baris isian soal pada halaman detail form ujian.

    Dipakai dua kali oleh `forms/show.blade.php`:
      1. dirender langsung oleh server (tiga baris kosong siap diisi), dan
      2. di dalam <template> yang dikloning tombol "+ Tambah soal".

    Saat dipakai di dalam template, `$index` berisi penanda `__INDEX__` yang
    diganti JavaScript dengan nomor baris sebenarnya. Karena baris kosong
    diabaikan controller, tiga baris kosong tidak pernah memicu error validasi.

    AGENT: asisten | 2026-10-20 | baris soal tanpa Alpine | modul QA Assessment
--}}
@php
    $oldQuestion = old("questions.$index.question");
    $oldType = old("questions.$index.question_type", 'essay');
    $oldPoint = old("questions.$index.point", 1);
    $oldOptions = old("questions.$index.options", ['', '', '', '']);
    $oldCorrect = (string) old("questions.$index.correct", '0');
    $isChoice = $oldType === 'multiple_choice';
@endphp

<div class="question-row space-y-3 rounded-lg border border-ink-200 p-4">
    <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-semibold uppercase tracking-wide text-ink-500">Soal</span>
        <button type="button" class="text-xs text-red-600 hover:underline" data-remove-row>Hapus baris</button>
    </div>

    <textarea class="form-input w-full" rows="2"
              name="questions[{{ $index }}][question]"
              placeholder="Tulis pertanyaan...">{{ $oldQuestion }}</textarea>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="form-label">Tipe soal</label>
            <select class="form-input w-full" name="questions[{{ $index }}][question_type]" data-question-type>
                <option value="essay" @selected($oldType === 'essay')>Essay (dinilai manual)</option>
                <option value="multiple_choice" @selected($oldType === 'multiple_choice')>Pilihan ganda</option>
            </select>
        </div>
        <div>
            <label class="form-label">Poin</label>
            <input type="number" min="0" class="form-input w-full"
                   name="questions[{{ $index }}][point]" value="{{ $oldPoint }}">
        </div>
    </div>

    <div class="space-y-2 {{ $isChoice ? '' : 'hidden' }}" data-options-block>
        <p class="form-label">
            Opsi jawaban
            <span class="font-normal text-ink-500">(pilih satu yang benar)</span>
        </p>
        @for ($optionIndex = 0; $optionIndex < 4; $optionIndex++)
            <div class="flex items-center gap-2">
                <input type="radio" class="h-4 w-4 text-brand-600"
                       name="questions[{{ $index }}][correct]"
                       value="{{ $optionIndex }}"
                       @checked($oldCorrect === (string) $optionIndex)>
                <span class="w-4 text-xs font-semibold text-ink-500">{{ chr(65 + $optionIndex) }}</span>
                <input type="text" class="form-input flex-1" data-option-input
                       name="questions[{{ $index }}][options][{{ $optionIndex }}]"
                       value="{{ $oldOptions[$optionIndex] ?? '' }}"
                       placeholder="Teks opsi {{ chr(65 + $optionIndex) }}">
            </div>
        @endfor
    </div>
</div>
