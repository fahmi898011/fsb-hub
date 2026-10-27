<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Kanal utama untuk seluruh karyawan (PRD Chat §6).
 *
 * Hanya SATU kanal yang dibuat seeder — "Umum". Kanal per divisi TIDAK
 * dibuat otomatis dan tidak disinkronkan dengan data divisi: PRD §6 memilih
 * keanggotaan manual, jadi pembuatan kanal selebihnya adalah keputusan
 * admin/qa, bukan efek samping seeder.
 */
class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@fsb.test')->first();

        $conversation = Conversation::firstOrCreate(
            ['type' => Conversation::TYPE_CHANNEL, 'name' => 'Umum'],
            [
                'description' => 'Kanal bersama untuk seluruh karyawan.',
                'is_private' => false,
                'created_by' => $admin?->getKey(),
            ],
        );

        // Semua user aktif dimasukkan sebagai anggota. `syncWithoutDetaching`
        // dipakai agar menjalankan ulang seeder tidak menghapus anggota yang
        // ditambahkan admin/qa secara manual, dan tidak menyentuh
        // `last_read_at` yang sudah ada.
        $ids = User::where('status', 'active')->pluck('id')->all();

        foreach ($ids as $id) {
            $conversation->members()->syncWithoutDetaching([
                $id => ['joined_at' => now()],
            ]);
        }
    }
}
