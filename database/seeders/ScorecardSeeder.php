<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\Department;
use App\Models\Kpi;
use App\Models\Scorecard;
use App\Models\ScorecardCategory;
use App\Models\ScorecardParameter;
use App\Models\ScorecardVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Master Scorecard awal dari 4 form Excel (PRD Employee KPI/QA §3.1).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Excel adalah REFERENSI/initial master; setelah ini database menjadi sumber
 * kebenaran (PRD §3.1). Seeder ini menanam satu versi awal (versi 1) untuk
 * tiap channel: Inbound, Outbound, Chat, Email.
 *
 * Data diambil dari `contoh-excel/New Form Attribute Score per Channel.xlsx`
 * (sheet Form INBOUND/OUTBOUND) dan gambar form Chat/Email di `docs/kpi-forms/`.
 *
 * AGENT: asisten | 2026-10-27 | catatan | folder `contoh-excel/` dan `docs/`
 * (termasuk `docs/kpi-forms/`) tidak ikut di-commit (lihat .gitignore) karena
 * hanya referensi di mesin lokal; data master sudah tertanam di seeder ini dan
 * database menjadi sumber kebenaran (PRD §3.1).
 *
 * Keputusan penting yang tercermin di data:
 * - Bobot mengikuti form. Total parameter normal + ZTP `weight_only` = 100%,
 *   sesuai syarat publish PRD §37. Baris ZTP `zero_score` berbobot 0 dan
 *   TIDAK ikut total karena hanya bertugas memaksa skor menjadi 0.
 * - Baris Zero Tolerance bermakna TERBALIK: jawaban "NO" = tidak ada
 *   pelanggaran = tidak ada penalty (`answer_polarity = inverted`). Karena itu
 *   ZTP TIDAK boleh dikenali dari nama parameter, melainkan dari kolom tipe.
 */
class ScorecardSeeder extends Seeder
{
    private int|null $divisionId = null;

    /** @var array<string, Channel> */
    private array $channels = [];

    public function run(): void
    {
        // Divisi pemilik scorecard. Memakai master `departments` yang ada
        // (bukan tabel `divisions` baru) agar hanya ada satu sumber kebenaran.
        $this->divisionId = Department::where('slug', Str::slug('Call Center'))->value('id');

        $this->channel('Inbound', 'Kanal panggilan masuk.', 1);
        $this->channel('Outbound', 'Kanal panggilan keluar.', 2);
        $this->channel('Chat', 'Kanal percakapan teks (live chat).', 3);
        $this->channel('Email', 'Kanal surat elektronik.', 4);

        foreach ([
            $this->inboundDefinition(),
            $this->outboundDefinition(),
            $this->chatDefinition(),
            $this->emailDefinition(),
        ] as $definition) {
            $this->apply($definition);
        }
    }

    private function channel(string $name, string $description, int $sortOrder): void
    {
        $this->channels[$name] = Channel::updateOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'description' => $description, 'status' => 'active', 'sort_order' => $sortOrder],
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function apply(array $definition): void
    {
        $channel = $this->channels[$definition['channel']];

        $kpi = Kpi::updateOrCreate(
            ['slug' => Str::slug($definition['kpi'])],
            [
                'department_id' => $this->divisionId,
                'name' => $definition['kpi'],
                'code' => 'QUALITY',
                'description' => 'Nilai mutu layanan hasil penilaian QA (sampling).',
                'status' => 'active',
                'sort_order' => 0,
            ],
        );

        $scorecard = Scorecard::updateOrCreate(
            ['slug' => Str::slug($definition['scorecard'])],
            [
                'kpi_id' => $kpi->id,
                'channel_id' => $channel->id,
                'name' => $definition['scorecard'],
                'description' => $definition['description'],
                'status' => 'active',
                'sort_order' => $channel->sort_order,
            ],
        );

        $version = ScorecardVersion::updateOrCreate(
            ['scorecard_id' => $scorecard->id, 'version' => 1],
            [
                'status' => 'active',
                'effective_from' => null,
                'effective_to' => null,
                'notes' => 'Versi awal, ditanam dari form Excel (referensi).',
                'published_at' => now(),
            ],
        );

        $categoryOrder = 0;

        foreach ($definition['categories'] as $categoryDefinition) {
            $category = ScorecardCategory::updateOrCreate(
                ['scorecard_version_id' => $version->id, 'name' => $categoryDefinition['name']],
                ['status' => 'active', 'sort_order' => $categoryOrder++],
            );

            $parameterOrder = 0;

            foreach ($categoryDefinition['params'] as $parameter) {
                $this->parameter($category->id, $parameter, $parameterOrder++);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private function parameter(int $categoryId, array $parameter, int $sortOrder): void
    {
        ScorecardParameter::updateOrCreate(
            ['scorecard_category_id' => $categoryId, 'name' => $parameter['name']],
            [
                'description' => $parameter['description'] ?? null,
                'weight' => $parameter['weight'] ?? 0,
                'evaluation_type' => $parameter['eval'] ?? 'yes_no',
                'parameter_type' => $parameter['type'] ?? 'normal',
                'ztp_effect' => $parameter['ztp'] ?? 'zero_score',
                'answer_polarity' => $parameter['polarity'] ?? 'normal',
                'guideline' => $parameter['guideline'] ?? null,
                'penalty_rule' => $parameter['penalty'] ?? null,
                'is_required' => $parameter['required'] ?? true,
                'status' => 'active',
                'sort_order' => $sortOrder,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function inboundDefinition(): array
    {
        return [
            'channel' => 'Inbound',
            'kpi' => 'Quality Score',
            'scorecard' => 'QA Scorecard - Quality Inbound',
            'description' => 'Scorecard penilaian QA untuk panggilan masuk (Inbound).',
            'categories' => [
                [
                    'name' => 'Opening & Closing',
                    'params' => [
                        ['name' => 'Salam Pembuka', 'weight' => 5],
                        ['name' => 'Salam penutup', 'weight' => 5],
                    ],
                ],
                [
                    'name' => 'Probing & Research',
                    'params' => [
                        ['name' => 'Empati', 'weight' => 6],
                        ['name' => 'Verifikasi Data', 'weight' => 5],
                        ['name' => 'Penggalian kebutuhan pelanggan untuk memahami permasalahan dan menawarkan bantuan lanjutan', 'weight' => 5],
                    ],
                ],
                [
                    'name' => 'Resolution / Accuracy',
                    'params' => [
                        ['name' => 'Ketepatan, kelengkapan informasi dan solusi', 'weight' => 15],
                        ['name' => 'Eskalasi', 'weight' => 5],
                        ['name' => 'Akurasi dan kelengkapan pencatatan dokumentasi layanan', 'weight' => 15],
                    ],
                ],
                [
                    'name' => 'Communication',
                    'params' => [
                        ['name' => 'Kesesuaian nada bicara dan gaya komunikasi', 'weight' => 5],
                        ['name' => 'Penggunaan tata bahasa, pemilihan kata yang tepat', 'weight' => 10],
                        ['name' => 'Standar Hold', 'weight' => 5],
                        ['name' => 'Penggunaan Jargon (Bahasa sehari-hari dan bahasa asing)', 'weight' => 5],
                        ['name' => 'Kemampuan Mendengarkan', 'weight' => 4],
                        ['name' => 'Tidak memotong pembicaraan', 'weight' => 4],
                        ['name' => 'Kesopanan', 'weight' => 3],
                        ['name' => 'Personalisasi', 'weight' => 3],
                    ],
                ],
                [
                    'name' => 'Zero Tolerance Policy / Unacceptable Behaviour',
                    'params' => [
                        array_merge($this->ztp(), ['name' => 'Zero Tolerance Policy']),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outboundDefinition(): array
    {
        return [
            'channel' => 'Outbound',
            'kpi' => 'Quality Score',
            'scorecard' => 'QA Scorecard - Quality Outbound',
            'description' => 'Scorecard penilaian QA untuk panggilan keluar (Outbound).',
            'categories' => [
                [
                    'name' => 'Opening & Closing',
                    'params' => [
                        ['name' => 'Greeting', 'weight' => 5],
                        ['name' => 'Izin Penyampaian Pernyataan', 'weight' => 2],
                        ['name' => 'Closing', 'weight' => 5],
                    ],
                ],
                [
                    'name' => 'Probing & Research',
                    'params' => [
                        ['name' => 'Verifikasi Kontak dan Identitas Pelanggan', 'weight' => 2],
                        ['name' => 'Penggalian kebutuhan pelanggan untuk memahami permasalahan dan menawarkan bantuan lanjutan', 'weight' => 5],
                    ],
                ],
                [
                    'name' => 'Resolution / Accuracy',
                    'params' => [
                        ['name' => 'Ketepatan, kelengkapan informasi dan solusi', 'weight' => 20],
                        ['name' => 'Did Agent need engagement?', 'weight' => 5],
                        ['name' => 'Akurasi dan kelengkapan pencatatan dokumentasi layanan', 'weight' => 10],
                        ['name' => 'Ketepatan waktu dalam melakukan tindak lanjut (follow up)', 'weight' => 5],
                    ],
                ],
                [
                    'name' => 'Communication',
                    'params' => [
                        ['name' => 'Kesesuaian nada bicara dan gaya komunikasi', 'weight' => 6],
                        ['name' => 'Penggunaan tata bahasa, pemilihan kata yang tepat, Typo, fillers dan tidak terbata bata', 'weight' => 10],
                        ['name' => 'Personalisasi Pelapor / Kesopanan / Penggunaan HOLD / Empati', 'weight' => 10],
                        ['name' => 'Tidak menggunakan kata kata JARGON', 'weight' => 10],
                        ['name' => 'Kemampuan mendengarkan dan komunikasi yang efektif, termasuk memberi kesempatan pelanggan menyampaikan informasi tanpa interupsi', 'weight' => 5],
                    ],
                ],
                [
                    'name' => 'Zero Tolerance Policy / Unacceptable Behaviour',
                    'params' => [
                        array_merge($this->ztp(), ['name' => 'Zero Tolerance Policy']),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function chatDefinition(): array
    {
        return [
            'channel' => 'Chat',
            'kpi' => 'Quality Score',
            'scorecard' => 'QA Scorecard - Quality Chat',
            'description' => 'Scorecard penilaian QA untuk kanal live chat (Non-Voice).',
            'categories' => [
                [
                    'name' => 'Struktur Chat',
                    'params' => [
                        ['name' => 'Greeting & Closing, CSAT dan Empati/Positif Response', 'weight' => 0],
                        ['name' => 'Verifikasi Data yang sesuai', 'weight' => 0],
                        ['name' => 'Konsistensi personalisasi pelapor 3 kali secara merata dan kesopanan', 'weight' => 10],
                    ],
                ],
                [
                    'name' => 'Resolution / Accuracy',
                    'params' => [
                        ['name' => 'Penggalian kebutuhan pelanggan untuk memahami permasalahan dan menawarkan bantuan lanjutan', 'weight' => 10],
                        ['name' => 'Ketepatan, kelengkapan informasi dan solusi', 'weight' => 30],
                        ['name' => 'Agen menjawab semua pertanyaan?', 'weight' => 10],
                        ['name' => 'Akurasi & Kelengkapan Dokumen? (Live Chat)', 'weight' => 10],
                    ],
                ],
                [
                    'name' => 'Communication',
                    'params' => [
                        ['name' => 'Penggunaan tata bahasa, Tanda baca & Pemilihan kata yang tepat, Salah ketik, Jargon dan Kemampuan komunikasi yang efektif', 'weight' => 0],
                        ['name' => 'Penggunaan hold (lebih dari 2 menit), telat respon pelapor (lebih dari 2 menit)', 'weight' => 10],
                        ['name' => 'Memberikan waktu kepada pelapor untuk penyampaian maksud dan tidak terburu-buru dalam menghubungi percakapan', 'weight' => 0],
                        ['name' => 'FRT (First Response Time) <= 1 Menit', 'weight' => 10],
                        ['name' => 'FCR (First Contact Resolution): masalah/pertanyaan pelapor diselesaikan pada chat pertama', 'weight' => 10],
                    ],
                ],
                [
                    'name' => 'Zero Tolerance Policy / Unacceptable Behaviour',
                    'params' => [
                        array_merge($this->ztp(), ['name' => 'Zero Tolerance Policy']),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emailDefinition(): array
    {
        return [
            'channel' => 'Email',
            'kpi' => 'Quality Score',
            'scorecard' => 'QA Scorecard - Quality Email',
            'description' => 'Scorecard penilaian QA untuk kanal email (Non-Voice).',
            'categories' => [
                [
                    'name' => 'Soft Skill',
                    'params' => [
                        ['name' => 'E-mail Structure (Struktur Pesan/Personalia/Penggunaan Template/Skrip)', 'weight' => 10],
                    ],
                ],
                [
                    'name' => 'Productivity',
                    'params' => [
                        ['name' => 'FRT (First Response Time) max 30 minutes', 'weight' => 10],
                        ['name' => 'Resolution Time', 'weight' => 10],
                        ['name' => 'FCR (First Contact Resolution)', 'weight' => 10],
                    ],
                ],
                [
                    'name' => 'Resolution / Accuracy',
                    'params' => [
                        ['name' => 'Ketepatan, Kelengkapan Informasi & Solusi', 'weight' => 30],
                        ['name' => 'Ketepatan Eskalasi kepada tim terkait sesuai dengan kebutuhan (Jika diperlukan)', 'weight' => 10],
                        ['name' => 'Akurasi & Kelengkapan Dokumentasi (Ticket Details)', 'weight' => 10],
                    ],
                ],
                [
                    'name' => 'Communication',
                    'params' => [
                        ['name' => 'Penggunaan tata bahasa, Tanda baca & Pemilihan kata yang tepat, Salah ketik, Jargon dan Kemampuan komunikasi yang efektif', 'weight' => 10],
                    ],
                ],
                [
                    'name' => 'Zero Tolerance Policy / Unacceptable Behaviour',
                    'params' => [
                        array_merge($this->ztp(), ['name' => 'Zero Tolerance Policy']),
                    ],
                ],
            ],
        ];
    }

    /**
     * Parameter Zero Tolerance standar: bermakna terbalik (jawaban YES = terjadi
     * pelanggaran = penalty) dan memaksa skor menjadi 0. Ditulis satu tempat
     * supaya 4 channel tidak bisa berbeda diam-diam.
     *
     * @return array<string, mixed>
     */
    private function ztp(): array
    {
        return [
            'weight' => 0,
            'type' => 'zero_tolerance',
            'ztp' => 'zero_score',
            'polarity' => 'inverted',
            'required' => true,
            'description' => 'Pelanggaran berat/tidak dapat diterima. Bila jawabannya YES, skor akhir menjadi 0.',
        ];
    }
}
