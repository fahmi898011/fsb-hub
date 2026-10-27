@extends('layouts.app')

{{-- Detail form ujian tertulis QA Assessment.

     Patokan halaman ini adalah FORM, bukan soal: satu form berisi banyak soal.
     Soal ditulis langsung di sini (beberapa sekaligus) dan/atau diambil dari
     bank soal bersama. Satu soal bank boleh dipakai beberapa form sekaligus;
     yang menempel ke form ini adalah baris pivot `exam_form_questions`.
     AGENT: asisten | 2026-10-20 | rombak alur soal & distribusi | modul QA Assessment
--}}

@section('title', 'Detail Form Ujian')

@section('content')
    @php
        $isDraft = $examForm->status === 'draft';
        // Hanya pembuat form yang boleh menyunting (policy ExamFormPolicy).
        // Tanpa penjagaan ini, QA lain melihat form isian yang akan berakhir 403.
        $canEdit = $isDraft && auth()->user()->can('update', $examForm);
        $totalPoints = $examForm->questions->sum('point');
    @endphp

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">{{ $examForm->title }}</h1>
            <p class="text-sm text-ink-500">
                {{ $examForm->questions->count() }} soal &middot; {{ $totalPoints }} poin &middot;
                {{ $examForm->duration_minutes ? $examForm->duration_minutes . ' menit' : 'Tanpa batas waktu' }} &middot;
                {{ $examForm->passing_score !== null ? 'Lulus ' . $examForm->passing_score . '%' : 'Tanpa ambang lulus' }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-status-badge :status="$examForm->status" />
            @if ($isDraft)
                @can('update', $examForm)
                    <a href="{{ route('qa.exam.forms.edit', $examForm) }}" class="btn-secondary btn-sm">Ubah</a>
                @endcan
                @can('publish', $examForm)
                    <form method="POST" action="{{ route('qa.exam.forms.publish', $examForm) }}" class="inline"
                          onsubmit="return confirm('Terbitkan form? Soal tidak bisa diubah setelah terbit.')">
                        @csrf
                        <button type="submit" class="btn-primary btn-sm">Terbitkan</button>
                    </form>
                @endcan
            @endif
            @can('delete', $examForm)
                <form method="POST" action="{{ route('qa.exam.forms.destroy', $examForm) }}" class="inline"
                      onsubmit="return confirm('Hapus form ini beserta soalnya?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-secondary btn-sm text-red-600"
                            @disabled($examForm->status !== 'draft')>Hapus</button>
                </form>
            @endcan
            {{-- Jalan keluar untuk form terbit yang soalnya masih kurang. --}}
            @can('unpublish', $examForm)
                <form method="POST" action="{{ route('qa.exam.forms.unpublish', $examForm) }}" class="inline"
                      onsubmit="return confirm('Kembalikan form ke draft supaya soalnya bisa diubah?')">
                    @csrf
                    <button type="submit" class="btn-secondary btn-sm">Kembalikan ke Draft</button>
                </form>
            @endcan
        </div>
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

    {{-- Pengaturan form --}}
    <div class="card card-body mb-5 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Deskripsi</p>
            <p class="whitespace-pre-line font-medium text-ink-900">{{ $examForm->description ?: '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Batas percobaan</p>
            <p class="font-medium text-ink-900">{{ $examForm->max_attempts ?: 'Tanpa batas' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Metode retake</p>
            <p class="font-medium capitalize text-ink-900">{{ $examForm->grading_method ?: '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Percobaan ulang</p>
            <p class="font-medium text-ink-900">{{ $examForm->allow_retake ? 'Diizinkan' : 'Tidak diizinkan' }}</p>
        </div>
    </div>

    {{-- ============================== SOAL ============================== --}}
    {{-- Satu form berisi BANYAK soal. Baris isian dirender oleh server (tiga
         baris kosong siap pakai), jadi berapa pun soal bisa ditulis sekaligus
         tanpa bergantung pada Alpine. Tombol "+ Tambah soal" menambah baris
         baru dari <template> di bawah, dan baris yang dikosongkan diabaikan
         controller. Sebelumnya hanya SATU baris yang tampil, sehingga form
         terasa seperti satu soal per form.
         AGENT: asisten | 2026-10-20 | banyak soal per form tanpa Alpine | modul QA Assessment --}}
    @php
        $oldRows = old('questions', []);
        $rowCount = max(3, is_array($oldRows) ? count($oldRows) : 0);
    @endphp

    <section class="mb-5">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-ink-900">Soal pada Form ({{ $examForm->questions->count() }})</h2>
                <p class="text-sm text-ink-500">
                    @if ($isDraft)
                        Satu form boleh berisi banyak soal. Baris yang dikosongkan diabaikan saat disimpan.
                    @else
                        Soal terkunci setelah form diterbitkan.
                        @can('unpublish', $examForm)
                            Perlu diubah? Tekan "Kembalikan ke Draft" di atas.
                        @endcan
                    @endif
                </p>
            </div>

            {{-- Dua jalur menulis soal, keduanya bermuara ke form ini: langsung di
                 bawah, atau lewat bank soal yang otomatis menempel ke form ini.
                 AGENT: asisten | 2026-10-20 | tautan tulis soal dari form | modul QA Assessment --}}
            @if ($canEdit)
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('qa.exam.question-bank.create', ['form_id' => $examForm->id]) }}"
                       class="btn-secondary btn-sm">Tulis soal di bank soal</a>

                    @if ($bankQuestions->isNotEmpty())
                        <x-secondary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'attach-question')">
                            Ambil Soal yang Sudah Ada ({{ $bankQuestions->count() }})
                        </x-secondary-button>
                    @endif
                </div>
            @endif
        </div>

        @if ($canEdit)
            <details class="card card-body mb-4" @if ($examForm->questions->isEmpty() || $errors->any()) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-ink-900">
                    + Tulis soal baru
                    <span class="font-normal text-ink-500">(bisa beberapa sekaligus)</span>
                </summary>

                <form method="POST" action="{{ route('qa.exam.forms.questions.bulk', $examForm) }}"
                      class="mt-4 space-y-4" data-question-composer>
                    @csrf
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs text-ink-500">
                            Tiga baris sudah disiapkan. Tambah baris bila soalnya lebih banyak.
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
                        <button type="submit" class="btn-primary">Simpan semua soal</button>
                    </div>
                </form>

                <template data-row-template>
                    @include('qa.exam.forms._question-row', ['index' => '__INDEX__'])
                </template>
            </details>

            @include('qa.exam.forms._composer-script')
        @endif


        @if ($examForm->questions->isEmpty())
            <x-empty-state title="Belum ada soal"
                           description="Tulis soal pertama di form ini — beberapa soal bisa dibuat sekaligus." />
        @else
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                            <tr>
                                <th class="w-10 px-5 py-3 font-semibold">No</th>
                                <th class="px-5 py-3 font-semibold">Soal</th>
                                <th class="px-5 py-3 font-semibold">Tipe</th>
                                <th class="px-5 py-3 font-semibold">Poin</th>
                                <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($examForm->questions as $question)
                                <tr class="hover:bg-ink-50">
                                    <td class="px-5 py-3 font-medium text-ink-900">{{ $loop->iteration }}</td>
                                    <td class="max-w-md px-5 py-3 text-ink-700">
                                        {{ \Illuminate\Support\Str::limit($question->question_snapshot, 90) }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-badge :variant="$question->question_type_snapshot === 'essay' ? 'amber' : 'brand'">
                                            {{ $question->question_type_snapshot === 'essay' ? 'Essay' : 'Pilihan Ganda' }}
                                        </x-badge>
                                    </td>
                                    <td class="px-5 py-3 text-ink-600">
                                        @if ($canEdit)
                                            <form method="POST"
                                                  action="{{ route('qa.exam.forms.questions.update', [$examForm, $question]) }}"
                                                  class="flex items-center gap-1">
                                                @csrf
                                                @method('PUT')
                                                <input type="number" name="point" min="0" value="{{ $question->point }}"
                                                       class="form-input w-16 px-2 py-1 text-sm" aria-label="Poin soal">
                                                <button type="submit" class="text-xs text-brand-700 hover:underline">Simpan</button>
                                            </form>
                                        @else
                                            {{ $question->point }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($canEdit)
                                            @can('update', $question->questionBank)
                                                <a href="{{ route('qa.exam.question-bank.edit', $question->questionBank) }}"
                                                   class="text-sm text-brand-700 hover:underline">Ubah</a>
                                            @endcan
                                            <form method="POST"
                                                  action="{{ route('qa.exam.forms.questions.destroy', [$examForm, $question]) }}"
                                                  class="ml-3 inline" onsubmit="return confirm('Hapus soal ini dari form?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-red-600 hover:underline">Hapus</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-ink-400">Terunci</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-ink-100 bg-ink-50/50">
                            <tr>
                                <td colspan="3" class="px-5 py-3 text-right text-xs uppercase tracking-wide text-ink-500">Total poin</td>
                                <td class="px-5 py-3 font-semibold text-ink-900">{{ $totalPoints }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif
    </section>


    {{-- ============================ PESERTA ============================ --}}
    @if ($examForm->status === 'published')
        <section class="mb-5">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-ink-900">Peserta ({{ $assignmentStats['total'] }})</h2>
                    <p class="text-sm text-ink-500">Tugaskan per orang, per tim, atau per divisi sekaligus.</p>
                </div>
                @if ($candidates->isNotEmpty())
                    <x-secondary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'add-participant')">
                        + Tambah Peserta
                    </x-secondary-button>
                @endif
            </div>

            <div class="mb-3 flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-ink-100 px-2.5 py-1 font-medium text-ink-700">
                    Belum mulai {{ $assignmentStats['not_started'] }}
                </span>
                <span class="rounded-full bg-amber-50 px-2.5 py-1 font-medium text-amber-700">
                    Dikerjakan {{ $assignmentStats['in_progress'] }}
                </span>
                <span class="rounded-full bg-brand-50 px-2.5 py-1 font-medium text-brand-700">
                    Perlu dinilai {{ $assignmentStats['waiting_grade'] }}
                </span>
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700">
                    Selesai {{ $assignmentStats['done'] }}
                </span>
            </div>

            @if ($examForm->assignments->isEmpty())
                <x-empty-state title="Belum ada peserta"
                               description="Tugaskan form ini ke agent agar mereka bisa mengerjakan." />
            @else
                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                                <tr>
                                    <th class="px-5 py-3 font-semibold">Agent</th>
                                    <th class="px-5 py-3 font-semibold">Divisi / Tim</th>
                                    <th class="px-5 py-3 font-semibold">Attempt</th>
                                    <th class="px-5 py-3 font-semibold">Status</th>
                                    <th class="px-5 py-3 font-semibold">Nilai</th>
                                    <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100">

                                @foreach ($examForm->assignments as $assignment)
                                    <tr class="hover:bg-ink-50">
                                        <td class="px-5 py-3">
                                            <p class="font-medium text-ink-900">{{ $assignment->agent?->name }}</p>
                                            <p class="text-xs text-ink-500">{{ $assignment->agent?->employee_id }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-ink-600">
                                            <p>{{ $assignment->agent?->division?->name ?? ($assignment->agent?->department ?: '—') }}</p>
                                            <p class="text-xs text-ink-500">
                                                {{ $assignment->agent?->team->pluck('name')->implode(', ') ?: 'Tanpa tim' }}
                                            </p>
                                        </td>
                                        <td class="px-5 py-3 text-ink-600">ke-{{ $assignment->attempt_ke }}</td>
                                        <td class="px-5 py-3">
                                            <x-badge :variant="match($assignment->status) {
                                                'published' => 'green',
                                                'graded' => 'brand',
                                                'submitted', 'in_progress' => 'amber',
                                                default => 'gray',
                                            }">{{ ucfirst(str_replace('_', ' ', $assignment->status)) }}</x-badge>
                                        </td>
                                        <td class="px-5 py-3 text-ink-600">
                                            @if ($assignment->percentage !== null)
                                                {{ rtrim(rtrim(number_format((float) $assignment->percentage, 2, '.', ''), '0'), '.') }}%
                                                @if ($assignment->result)
                                                    <span class="ml-1 text-xs {{ $assignment->result === 'pass' ? 'text-emerald-600' : 'text-red-600' }}">
                                                        {{ $assignment->result === 'pass' ? 'Lulus' : 'Tidak lulus' }}
                                                    </span>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            @if (in_array($assignment->status, ['submitted', 'graded', 'published']))
                                                @can('grade', $assignment)
                                                    <a href="{{ route('qa.exam.grading.show', $assignment) }}" class="text-brand-700 hover:underline">Nilai</a>
                                                @else
                                                    <a href="{{ route('qa.exam.forms.result', $assignment) }}" class="text-brand-700 hover:underline">Hasil</a>
                                                @endcan
                                            @endif
                                            @can('create', App\Models\ExamAssignment::class)
                                                @if ($examForm->allow_retake)
                                                    <form method="POST"
                                                          action="{{ route('qa.exam.forms.assignments.retake', [$examForm, $assignment]) }}"
                                                          class="ml-3 inline" onsubmit="return confirm('Buat percobaan ulang untuk agent ini?')">
                                                        @csrf
                                                        <button type="submit" class="text-sm text-brand-700 hover:underline">Retake</button>
                                                    </form>
                                                @endif
                                            @endcan
                                            @can('delete', $assignment)
                                                <form method="POST"
                                                      action="{{ route('qa.exam.forms.assignments.destroy', [$examForm, $assignment]) }}"
                                                      class="ml-3 inline" onsubmit="return confirm('Hapus penugasan ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-sm text-red-600 hover:underline">Hapus</button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    @endif

    <div class="mt-5">
        <a href="{{ route('qa.exam.forms.index') }}" class="btn-secondary">Kembali</a>
    </div>


    {{-- Modal: ambil soal yang sudah ada (multi-pilih) --}}
    @if ($canEdit && $bankQuestions->isNotEmpty())
        <x-modal name="attach-question" focusable maxWidth="lg">
            <form method="POST" action="{{ route('qa.exam.forms.questions.store', $examForm) }}" class="space-y-4 p-6">
                @csrf
                <div>
                    <h2 class="text-lg font-semibold text-ink-900">Ambil Soal yang Sudah Ada</h2>
                    <p class="mt-1 text-sm text-ink-500">
                        Centang beberapa soal sekaligus. Semua soal aktif di bank soal yang belum ada di form ini
                        bisa dipakai &mdash; soal yang sudah dipakai form lain tetap boleh dipakai di sini.
                    </p>
                </div>

                <div class="max-h-72 divide-y divide-ink-100 overflow-y-auto rounded-lg border border-ink-200">
                    @foreach ($bankQuestions as $bq)
                        <label class="flex cursor-pointer items-start gap-3 px-3 py-2 hover:bg-ink-50">
                            <input type="checkbox" name="question_bank_ids[]" value="{{ $bq->id }}"
                                   class="mt-1 h-4 w-4 rounded border-ink-300 text-brand-600">
                            <span class="min-w-0">
                                <span class="block text-sm text-ink-800">{{ \Illuminate\Support\Str::limit($bq->question, 110) }}</span>
                                <span class="block text-xs text-ink-500">
                                    Dipakai di {{ $bq->form_questions_count }} form
                                    &middot; {{ $bq->category?->name ?? 'Tanpa kategori' }}
                                    &middot; {{ $bq->point }} poin
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'attach-question')">Batal</x-secondary-button>
                    <x-primary-button>Tambah ke form</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif


    {{-- Modal: distribusi peserta (per tim / per divisi / per orang) --}}
    @if ($examForm->status === 'published' && $candidates->isNotEmpty())
        <x-modal name="add-participant" focusable maxWidth="2xl">
            <div class="space-y-5 p-6"
                 x-data="{
                     query: '',
                     division: '',
                     team: '',
                     selected: [],
                     agents: @js($candidatePayload),
                     get visible() {
                         const needle = this.query.trim().toLowerCase();

                         return this.agents.filter((agent) =>
                             (! needle || agent.name.toLowerCase().includes(needle)
                                 || (agent.employee_id || '').toLowerCase().includes(needle))
                             && (! this.division || String(agent.division_id) === this.division)
                             && (! this.team || agent.team_ids.includes(Number(this.team)))
                         );
                     },
                     get allVisibleSelected() {
                         return this.visible.length > 0
                             && this.visible.every((agent) => this.selected.includes(String(agent.id)));
                     },
                     toggleAll() {
                         const ids = this.visible.map((agent) => String(agent.id));

                         this.selected = this.allVisibleSelected
                             ? this.selected.filter((id) => ! ids.includes(id))
                             : [...new Set([...this.selected, ...ids])];
                     },
                 }">
                <div>
                    <h2 class="text-lg font-semibold text-ink-900">Tambah Peserta</h2>
                    <p class="mt-1 text-sm text-ink-500">
                        Paling cepat: tugaskan satu tim atau satu divisi. Bisa juga pilih orang per orang.
                    </p>
                </div>

                {{-- Per tim --}}
                <form method="POST" action="{{ route('qa.exam.forms.assignments.store', $examForm) }}"
                      class="flex flex-wrap items-end gap-2 rounded-lg border border-ink-200 bg-ink-50/40 p-3">
                    @csrf
                    <div class="min-w-[14rem] flex-1">
                        <label class="form-label">Seluruh tim</label>
                        <select name="team_id" class="form-input w-full" required>
                            <option value="">-- Pilih tim --</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">
                                    {{ $team->name }}@if ($team->department) — {{ $team->department->name }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-secondary">Tugaskan tim</button>
                </form>

                {{-- Per divisi --}}
                <form method="POST" action="{{ route('qa.exam.forms.assignments.store', $examForm) }}"
                      class="flex flex-wrap items-end gap-2 rounded-lg border border-ink-200 bg-ink-50/40 p-3">
                    @csrf
                    <div class="min-w-[14rem] flex-1">
                        <label class="form-label">Seluruh divisi</label>
                        <select name="division_id" class="form-input w-full" required>
                            <option value="">-- Pilih divisi --</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-secondary">Tugaskan divisi</button>
                </form>


                {{-- Per orang --}}
                <form method="POST" action="{{ route('qa.exam.forms.assignments.store', $examForm) }}" class="space-y-3">
                    @csrf
                    <div class="grid gap-2 sm:grid-cols-3">
                        <div>
                            <label class="form-label">Cari karyawan</label>
                            <input type="search" class="form-input w-full" placeholder="Nama atau NIP" x-model="query">
                        </div>
                        <div>
                            <label class="form-label">Divisi</label>
                            <select class="form-input w-full" x-model="division">
                                <option value="">Semua divisi</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Tim</label>
                            <select class="form-input w-full" x-model="team">
                                <option value="">Semua tim</option>
                                @foreach ($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-ink-500">
                        <span>
                            <span x-text="visible.length"></span> karyawan cocok &middot;
                            <span x-text="selected.length"></span> dipilih
                        </span>
                        <button type="button" class="font-medium text-brand-700 hover:underline" x-on:click="toggleAll()"
                                x-text="allVisibleSelected ? 'Batalkan pilihan yang tampil' : 'Pilih semua yang tampil'"></button>
                    </div>

                    <div class="max-h-64 divide-y divide-ink-100 overflow-y-auto rounded-lg border border-ink-200">
                        <template x-for="agent in visible" :key="agent.id">
                            <label class="flex cursor-pointer items-start gap-3 px-3 py-2 hover:bg-ink-50">
                                <input type="checkbox" name="agent_ids[]" :value="String(agent.id)" x-model="selected"
                                       class="mt-1 h-4 w-4 rounded border-ink-300 text-brand-600">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-ink-900" x-text="agent.name"></span>
                                    <span class="block truncate text-xs text-ink-500"
                                          x-text="[agent.employee_id, agent.division, agent.teams].filter(Boolean).join(' · ')"></span>
                                </span>
                            </label>
                        </template>
                        <p x-show="visible.length === 0" class="px-3 py-6 text-center text-sm text-ink-500">
                            Tidak ada karyawan yang cocok dengan filter.
                        </p>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2 pt-1">
                        <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'add-participant')">Batal</x-secondary-button>
                        <x-primary-button>Tugaskan <span x-text="selected.length"></span> peserta</x-primary-button>
                    </div>
                </form>
            </div>
        </x-modal>
    @endif
@endsection

