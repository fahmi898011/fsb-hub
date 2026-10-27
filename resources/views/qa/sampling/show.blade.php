@extends('layouts.app')

{{-- Halaman pengisian assessment QA Sampling (PRD §16/§43/§44).
     Tampilan mengutamakan kecepatan QA: jawaban disimpan begitu dipilih,
     dan skor yang tampil selalu berasal dari server. --}}
{{-- AGENT: asisten | 2026-10-03 | buat berkas baru | modul KPI, fase 2 QA sampling --}}
{{-- AGENT: asisten | 2026-10-03 | ubah: tombol jawaban memakai fetch() (tanpa reload) + fallback anchor | modul KPI, fase 2 QA sampling --}}

@section('title', 'Assessment '.($assessment->employee_name_snapshot ?? ''))

@section('content')
    @php
        $statusMap = [
            'draft' => ['amber', 'Draft'],
            'submitted' => ['brand', 'Terkirim'],
            'reviewed' => ['brand', 'Diperiksa'],
            'locked' => ['green', 'Terkunci'],
            'cancelled' => ['gray', 'Dibatalkan'],
        ];
        [$statusVariant, $statusLabel] = $statusMap[$assessment->status] ?? ['gray', $assessment->status];
        $scored = $itemsByCategory->flatten()->whereNotNull('answer')->count();
        $total = $itemsByCategory->flatten()->count();
    @endphp

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">
                {{ $assessment->employee_name_snapshot ?? 'Assessment' }}
            </h1>
            <p class="text-sm text-ink-500">
                {{ $assessment->scorecard_name_snapshot }}
                @if ($assessment->version_number_snapshot) &middot; Versi {{ $assessment->version_number_snapshot }} @endif
                @if ($assessment->channel_name_snapshot) &middot; {{ $assessment->channel_name_snapshot }} @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
            <a href="{{ route('qa.sampling.index') }}" class="btn-secondary btn-sm">Kembali</a>
        </div>
    </div>

    {{-- Ringkasan skor (PRD §43/§44). Angka di bawah dirender server DAN
         diperbarui langsung oleh JavaScript setiap kali jawaban berubah; karena
         nilai awal pun berasal dari server, tampilannya tetap benar walau
         JavaScript mati. --}}
    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="card card-body">
            <p class="text-xs uppercase tracking-wide text-ink-400">Skor Awal</p>
            <p id="summary-initial" class="mt-1 text-lg font-semibold text-ink-900">{{ number_format((float) $assessment->initial_score, 2) }}%</p>
        </div>
        <div class="card card-body">
            <p class="text-xs uppercase tracking-wide text-ink-400">Total Penalty</p>
            <p id="summary-penalty" class="mt-1 text-lg font-semibold text-ink-900">{{ number_format((float) $assessment->penalty_total, 2) }}%</p>
        </div>
        <div class="card card-body">
            <p class="text-xs uppercase tracking-wide text-ink-400">Skor Akhir</p>
            <p id="summary-final" class="mt-1 text-lg font-semibold {{ $assessment->zero_tolerance_triggered ? 'text-red-600' : 'text-green-600' }}">
                {{ $assessment->final_score !== null ? number_format((float) $assessment->final_score, 2).'%' : '—' }}
            </p>
            <p id="summary-final-note" class="text-xs text-red-500 {{ $assessment->zero_tolerance_triggered ? '' : 'hidden' }}">Zero Tolerance terpicu &rarr; skor 0.</p>
        </div>
        <div class="card card-body">
            <p class="text-xs uppercase tracking-wide text-ink-400">Terjawab</p>
            <p class="mt-1 text-lg font-semibold text-ink-900"><span id="summary-scored">{{ $scored }}</span> / <span id="summary-total">{{ $total }}</span></p>
            <p class="text-xs text-ink-400">Parameter wajib harus terisi sebelum dikirim.</p>
        </div>
    </div>

    @php
        $sampleLink = $assessment->sample_link;
    @endphp

    {{-- Informasi sample & konteks (PRD §15/§43) --}}
    <div class="card card-body mb-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-400">Tanggal Penilaian</p>
            <p class="mt-1 text-ink-900">{{ $assessment->assessment_date?->format('d M Y') ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-400">QA Penilai</p>
            <p class="mt-1 text-ink-900">{{ $assessment->qa?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-400">Divisi</p>
            <p class="mt-1 text-ink-900">{{ $assessment->division?->name ?? '—' }}</p>
        </div>
        <div class="sm:col-span-3">
            <p class="text-xs uppercase tracking-wide text-ink-400">Sample</p>
            @if ($sampleLink)
                <a href="{{ $sampleLink }}" target="_blank" rel="noopener noreferrer"
                   class="mt-1 inline-block break-all text-brand-700 hover:underline">{{ $sampleLink }}</a>
            @else
                <p class="mt-1 text-ink-400">Tautan sample belum diisi.</p>
            @endif
            @if ($assessment->sample_id)
                <p class="text-xs text-ink-400">ID Sample: {{ $assessment->sample_id }}</p>
            @endif
        </div>
    </div>

    {{-- Daftar parameter per kategori (PRD §43). --}}
    @forelse ($itemsByCategory as $category => $items)
        <div class="card mb-4 overflow-hidden">
            <div class="border-b border-ink-100 bg-ink-50/70 px-5 py-3">
                <h2 class="text-sm font-semibold text-ink-900">{{ $category }}</h2>
            </div>

            <ul class="divide-y divide-ink-100">
                @foreach ($items as $item)
                    @php
                        $typeMap = [
                            'normal' => ['gray', 'Normal'],
                            'critical' => ['amber', 'Critical'],
                            'zero_tolerance' => ['red', 'Zero Tolerance'],
                        ];
                        [$typeVariant, $typeLabel] = $typeMap[$item->parameter_type_snapshot] ?? ['gray', $item->parameter_type_snapshot];
                        $current = $item->answer;
                    @endphp
                    <li id="item-{{ $item->id }}" class="scroll-mt-24 px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink-900">
                                    {{ $item->parameter_name_snapshot }}
                                    @unless ($item->is_required_snapshot)
                                        <span class="ml-1 text-xs font-normal text-ink-400">(opsional)</span>
                                    @endunless
                                </p>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <x-badge :variant="$typeVariant">{{ $typeLabel }}</x-badge>
                                    <span class="text-xs text-ink-500">Bobot {{ number_format((float) $item->weight_snapshot, 2) }}%</span>
                                    {{-- Badge penalti selalu ada di DOM (walau kosong) supaya
                                         JavaScript cukup mengubah isi & visibilitasnya saat
                                         jawaban berubah, tanpa menyusun ulang markup. --}}
                                    <x-badge variant="red" data-penalty class="{{ $item->is_violation ? '' : 'hidden' }}">
                                        @if ($item->isZeroToleranceScoreZero())
                                            ZTP &rarr; skor 0
                                        @else
                                            Penalty {{ number_format((float) $item->penalty, 2) }}%
                                        @endif
                                    </x-badge>
                                </div>
                                @if ($item->comment)
                                    <p class="mt-2 text-xs text-ink-500">Catatan: {{ $item->comment }}</p>
                                @endif
                            </div>

                            @if ($editable)
                                {{-- Jawaban dikirim begitu dipilih. Tanpa JavaScript form ini
                                     tersubmit seperti biasa (server menghitung ulang skor lalu
                                     menggulir ke #item-<id>). Dengan JavaScript, Alpine menahan
                                     submit, mengirim lewat fetch(), lalu memperbarui tombol,
                                     badge penalti, dan ringkasan skor TANPA memuat ulang. --}}
                                <form method="POST" action="{{ route('qa.sampling.answer', $assessment) }}" class="shrink-0"
                                      data-answer-form
                                      x-data="{
                                          current: @js($current),
                                          busy: false,
                                          error: null,
                                          async save(value) {
                                              const previous = this.current;
                                              this.current = value;
                                              this.busy = true;
                                              this.error = null;
                                              const body = new FormData(this.$el);
                                              body.set('answer', value);
                                              try {
                                                  const response = await fetch(this.$el.action, {
                                                      method: 'POST',
                                                      headers: { 'Accept': 'application/json' },
                                                      body,
                                                  });
                                                  if (! response.ok) {
                                                      // Harus selalu ada jawaban yang terlihat, jadi
                                                      // tampilan dikembalikan ke jawaban sebelumnya.
                                                      this.current = previous;
                                                      this.error = 'Jawaban gagal disimpan. Coba lagi.';
                                                  } else {
                                                      applyAnswer(await response.json());
                                                  }
                                              } catch (e) {
                                                  this.current = previous;
                                                  this.error = 'Tidak dapat menghubungi server.';
                                              } finally {
                                                  this.busy = false;
                                              }
                                          },
                                      }"
                                      @submit.prevent="save($event.submitter?.value)">
                                    @csrf
                                    <input type="hidden" name="item_id" value="{{ $item->id }}">
                                    <div class="inline-flex overflow-hidden rounded-lg border border-ink-200">
                                        @foreach (['yes' => 'YES', 'no' => 'NO'] as $value => $label)
                                            <button type="submit" name="answer" value="{{ $value }}"
                                                    :disabled="busy"
                                                    @click="current = '{{ $value }}'"
                                                    :class="current === '{{ $value }}'
                                                        ? 'bg-brand-600 text-white'
                                                        : 'bg-white text-ink-600 hover:bg-ink-50'"
                                                    class="px-3 py-1.5 text-xs font-semibold transition disabled:opacity-60">
                                                {{ $label }}
                                            </button>
                                        @endforeach
                                        @if ($item->parameter_type_snapshot !== 'zero_tolerance')
                                            <button type="submit" name="answer" value="na"
                                                    :disabled="busy"
                                                    @click="current = 'na'"
                                                    :class="current === 'na'
                                                        ? 'bg-brand-600 text-white'
                                                        : 'bg-white text-ink-600 hover:bg-ink-50'"
                                                    class="border-l border-ink-200 px-3 py-1.5 text-xs font-semibold transition disabled:opacity-60">
                                                NA
                                            </button>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-right text-xs text-red-600" x-show="error" x-cloak x-text="error"></p>
                                </form>
                            @else
                                @php
                                    $answerMap = ['yes' => ['green', 'YES'], 'no' => ['red', 'NO'], 'na' => ['gray', 'NA']];
                                    [$answerVariant, $answerLabel] = $answerMap[$current] ?? ['gray', 'Belum dinilai'];
                                @endphp
                                <x-badge :variant="$answerVariant">{{ $answerLabel }}</x-badge>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <x-empty-state title="Versi scorecard tanpa parameter"
                       description="Scorecard ini belum memiliki parameter aktif, sehingga tidak ada yang dapat dinilai." />
    @endforelse

    {{-- Catatan umum assessment (PRD §15 "notes"). --}}
    @if ($assessment->notes)
        <div class="card card-body mb-4">
            <p class="text-xs uppercase tracking-wide text-ink-400">Catatan Umum</p>
            <p class="mt-1 whitespace-pre-line text-sm text-ink-700">{{ $assessment->notes }}</p>
        </div>
    @endif

    @if ($assessment->status === 'cancelled' && $assessment->cancelled_reason)
        <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
            Dibatalkan: {{ $assessment->cancelled_reason }}
        </div>
    @endif

    {{-- Panel aksi: alur draft -> submitted -> reviewed -> locked (+cancelled). --}}
    <div class="card card-body">
        <div class="flex flex-wrap items-center gap-2">
            @can('submit', $assessment)
                <form method="POST" action="{{ route('qa.sampling.submit', $assessment) }}">
                    @csrf
                    <button type="submit" class="btn-primary">Kirim untuk Diperiksa</button>
                </form>
            @endcan

            @can('review', $assessment)
                <form method="POST" action="{{ route('qa.sampling.review', $assessment) }}">
                    @csrf
                    <button type="submit" class="btn-primary">Tandai Sudah Diperiksa</button>
                </form>
            @endcan

            @can('lock', $assessment)
                <form method="POST" action="{{ route('qa.sampling.lock', $assessment) }}">
                    @csrf
                    <button type="submit" class="btn-secondary">Kunci Assessment</button>
                </form>
            @endcan

            @can('delete', $assessment)
                <form method="POST" action="{{ route('qa.sampling.destroy', $assessment) }}"
                      onsubmit="return confirm('Hapus draft assessment ini?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-ghost text-red-600 hover:bg-red-50">Hapus Draft</button>
                </form>
            @endcan
        </div>

        @can('cancel', $assessment)
            <form method="POST" action="{{ route('qa.sampling.cancel', $assessment) }}" class="mt-4 border-t border-ink-100 pt-4"
                  onsubmit="return confirm('Batalkan assessment ini? Alasan wajib diisi.');">
                @csrf
                <label for="cancelled_reason" class="form-label">Batalkan Assessment</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input id="cancelled_reason" name="cancelled_reason" type="text" class="form-input sm:flex-1"
                           placeholder="Alasan pembatalan (mis. salah memilih karyawan)">
                    <button type="submit" class="btn-danger sm:shrink-0">Batalkan</button>
                </div>
                @error('cancelled_reason')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
            </form>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        // Memperbarui tampilan setelah server menyimpan jawaban.
        //
        // Dipanggil dari x-data pada tiap form jawaban (lihat `save()` di atas).
        // Sengaja global, bukan per-komponen: ringkasan skor di atas halaman
        // adalah SATU elemen bersama, jadi tidak masuk akal bila tiap item
        // menyimpan salinannya sendiri.
        //
        // Angka yang ditampilkan SELALU berasal dari balasan server (PRD §10:
        // skor tidak pernah dihitung di browser).
        window.applyAnswer = function (payload) {
            const item = payload.item || {};
            const summary = payload.summary || {};
            const row = document.getElementById('item-' + item.id);

            if (row) {
                // Badge penalti: teksnya mengikuti hasil hitung ulang server.
                const badge = row.querySelector('[data-penalty]');
                if (badge) {
                    if (item.is_violation) {
                        badge.textContent = item.zero_score
                            ? 'ZTP → skor 0'
                            : 'Penalty ' + Number(item.penalty).toFixed(2) + '%';
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
            }

            const setText = (selector, value) => {
                const el = document.querySelector(selector);
                if (el) el.textContent = value;
            };

            setText('#summary-initial', Number(summary.initial_score).toFixed(2) + '%');
            setText('#summary-penalty', Number(summary.penalty_total).toFixed(2) + '%');
            setText('#summary-scored', summary.scored);

            const finalEl = document.querySelector('#summary-final');
            if (finalEl) {
                finalEl.textContent = Number(summary.final_score).toFixed(2) + '%';
                finalEl.classList.toggle('text-red-600', summary.zero_tolerance_triggered);
                finalEl.classList.toggle('text-green-600', ! summary.zero_tolerance_triggered);
            }

            // Catatan Zero Tolerance hanya relevan saat terpicu.
            const note = document.querySelector('#summary-final-note');
            if (note) note.classList.toggle('hidden', ! summary.zero_tolerance_triggered);
        };
    </script>
@endpush
