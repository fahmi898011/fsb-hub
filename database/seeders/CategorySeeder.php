<?php

namespace Database\Seeders;

use App\Models\InformationCategory;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Juknis', 'icon' => 'book-open', 'color' => '#3b82f6', 'description' => 'Petunjuk teknis dan panduan kerja.'],
            ['name' => 'SOP', 'icon' => 'clipboard-list', 'color' => '#ef4444', 'description' => 'Standar operasional prosedur.'],
            ['name' => 'Script', 'icon' => 'headset', 'color' => '#8b5cf6', 'description' => 'Script komunikasi layanan.'],
            ['name' => 'Template', 'icon' => 'document-text', 'color' => '#10b981', 'description' => 'Template dokumen kerja.'],
            ['name' => 'FAQ', 'icon' => 'question-mark-circle', 'color' => '#f59e0b', 'description' => 'Pertanyaan yang sering diajukan.'],
            ['name' => 'Announcement', 'icon' => 'megaphone', 'color' => '#ec4899', 'description' => 'Pengumuman terbaru.'],
        ];

        foreach ($categories as $index => $category) {
            InformationCategory::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($category['name'])],
                array_merge($category, [
                    'status' => 'active',
                    'sort_order' => $index + 1,
                ])
            );
        }
    }
}
