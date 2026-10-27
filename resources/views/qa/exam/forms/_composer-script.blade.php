{{--
    Skrip penyusun soal massal: satu halaman boleh berisi banyak soal.

    Dipakai halaman detail form ujian dan halaman tulis soal di bank soal.
    Syarat markup:
      - formulir ber-atribut `data-question-composer`,
      - wadah baris `[data-question-rows]` (satu baris = `.question-row`),
      - tombol `[data-add-row]`,
      - satu `<template data-row-template>` di halaman yang sama.

    AGENT: asisten | 2026-10-20 | satu skrip penyusun soal | modul QA Assessment
--}}
<script>
    (function () {
        const composer = document.querySelector('[data-question-composer]');
        if (! composer) return;

        const rowsBox = composer.querySelector('[data-question-rows]');
        const template = document.querySelector('[data-row-template]');
        const addBtn = composer.querySelector('[data-add-row]');
        const counter = composer.querySelector('[data-row-count]');

        if (! rowsBox) return;

        function reindex() {
            const rows = rowsBox.querySelectorAll('.question-row');

            rows.forEach(function (row, position) {
                row.querySelectorAll('[name]').forEach(function (input) {
                    input.name = input.name.replace(/^questions\[[^\]]*\]/, 'questions[' + position + ']');
                });
            });

            if (counter) counter.textContent = rows.length;
        }

        function toggleOptions(row) {
            const type = row.querySelector('[data-question-type]');
            const block = row.querySelector('[data-options-block]');
            const isChoice = type && type.value === 'multiple_choice';

            if (block) block.classList.toggle('hidden', ! isChoice);

            row.querySelectorAll('[data-option-input]').forEach(function (input, index) {
                input.required = isChoice && index < 2;
            });
        }

        composer.addEventListener('change', function (event) {
            if (event.target.matches('[data-question-type]')) {
                toggleOptions(event.target.closest('.question-row'));
            }
        });

        composer.addEventListener('click', function (event) {
            if (! event.target.matches('[data-remove-row]')) return;

            if (rowsBox.querySelectorAll('.question-row').length <= 1) {
                alert('Minimal satu baris soal.');
                return;
            }

            event.target.closest('.question-row').remove();
            reindex();
        });

        if (addBtn && template) {
            addBtn.addEventListener('click', function () {
                const nextIndex = rowsBox.querySelectorAll('.question-row').length;
                const fragment = template.content.cloneNode(true);

                fragment.querySelectorAll('[name]').forEach(function (input) {
                    input.name = input.name.replace('__INDEX__', String(nextIndex));
                });

                rowsBox.appendChild(fragment);
                reindex();
            });
        }

        rowsBox.querySelectorAll('.question-row').forEach(toggleOptions);
    })();
</script>
