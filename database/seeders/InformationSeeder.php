<?php

namespace Database\Seeders;

use App\Models\InformationCategory;
use App\Models\InformationItem;
use App\Models\InformationVersion;
use App\Models\User;
use Illuminate\Database\Seeder;

class InformationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@fsb.test')->first();

        $items = [
            [
                'category' => 'Juknis',
                'title' => 'Juknis Program MBG',
                'document_number' => 'JUK-001',
                'description' => 'Panduan teknis pelaksanaan layanan call center program MBG.',
                'version' => '1.2',
                'change_notes' => 'Update bagian greeting dan escalation.',
                'views' => 1245,
                'downloads' => 320,
            ],
            [
                'category' => 'Juknis',
                'title' => 'Juknis Penanganan Komplain',
                'document_number' => 'JUK-002',
                'description' => 'Petunjuk teknis penanganan komplain pelanggan.',
                'version' => '1.0',
                'change_notes' => 'Initial release.',
                'views' => 980,
                'downloads' => 210,
            ],
            [
                'category' => 'Juknis',
                'title' => 'Juknis Layanan Pelanggan',
                'document_number' => 'JUK-003',
                'description' => 'Panduan standar layanan pelanggan FSB.',
                'version' => '1.1',
                'change_notes' => 'Revisi alur eskalasi.',
                'views' => 720,
                'downloads' => 150,
            ],
            [
                'category' => 'SOP',
                'title' => 'SOP Penanganan Komplain',
                'document_number' => 'SOP-001',
                'description' => 'Prosedur standar penanganan komplain.',
                'version' => '2.0',
                'change_notes' => 'Penambahan SLA penanganan.',
                'views' => 860,
                'downloads' => 190,
            ],
            [
                'category' => 'Script',
                'title' => 'Script Greeting',
                'document_number' => 'SCR-001',
                'description' => 'Script pembuka percakapan dengan pelanggan.',
                'version' => '1.0',
                'change_notes' => 'Initial release.',
                'views' => 640,
                'downloads' => 120,
            ],
            [
                'category' => 'Script',
                'title' => 'Script Layanan MBG',
                'document_number' => 'SCR-002',
                'description' => 'Script layanan program MBG.',
                'version' => '1.3',
                'change_notes' => 'Update informasi produk.',
                'views' => 530,
                'downloads' => 98,
            ],
            [
                'category' => 'Template',
                'title' => 'Template Monitoring',
                'document_number' => 'TPL-001',
                'description' => 'Template monitoring harian agent.',
                'version' => '1.0',
                'change_notes' => 'Initial release.',
                'views' => 300,
                'downloads' => 75,
            ],
            [
                'category' => 'FAQ',
                'title' => 'FAQ Layanan',
                'document_number' => 'FAQ-001',
                'description' => 'Pertanyaan yang sering diajukan seputar layanan.',
                'version' => '1.0',
                'change_notes' => 'Initial release.',
                'views' => 410,
                'downloads' => 60,
            ],
            [
                'category' => 'Announcement',
                'title' => 'Update Script Layanan',
                'document_number' => 'ANN-001',
                'description' => 'Pengumuman pembaruan script layanan per 8 September 2026.',
                'version' => '1.0',
                'change_notes' => 'Pengumuman resmi.',
                'views' => 220,
                'downloads' => 30,
            ],
        ];

        foreach ($items as $data) {
            $category = InformationCategory::where('slug', \Illuminate\Support\Str::slug($data['category']))->first();

            if (! $category) {
                continue;
            }

            $item = InformationItem::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($data['title'])],
                [
                    'category_id' => $category->id,
                    'title' => $data['title'],
                    'document_number' => $data['document_number'],
                    'description' => $data['description'],
                    'status' => 'published',
                    'published_at' => now()->subDays(rand(1, 30)),
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ]
            );

            $version = InformationVersion::updateOrCreate(
                ['information_id' => $item->id, 'version' => $data['version']],
                [
                    'change_notes' => $data['change_notes'],
                    'file_name' => \Illuminate\Support\Str::slug($data['title'], '_') . '_v' . $data['version'] . '.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => rand(200, 2000) * 1024,
                    'created_by' => $admin?->id,
                ]
            );

            $item->update(['current_version_id' => $version->id]);

            // dummy tracking (tanpa user)
            $views = $data['views'];
            $downloads = $data['downloads'];
            $now = now();

            $viewRows = [];
            for ($i = 0; $i < min($views, 50); $i++) {
                $viewRows[] = [
                    'information_id' => $item->id,
                    'version_id' => $version->id,
                    'user_id' => null,
                    'viewed_at' => $now->copy()->subMinutes(rand(1, 40000)),
                ];
            }
            if ($viewRows) {
                \App\Models\DocumentView::insert($viewRows);
            }

            $downloadRows = [];
            for ($i = 0; $i < min($downloads, 20); $i++) {
                $downloadRows[] = [
                    'information_id' => $item->id,
                    'version_id' => $version->id,
                    'user_id' => null,
                    'downloaded_at' => $now->copy()->subMinutes(rand(1, 40000)),
                ];
            }
            if ($downloadRows) {
                \App\Models\Download::insert($downloadRows);
            }
        }
    }
}
