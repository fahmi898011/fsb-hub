<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Divisi & Tim awal (PRD KPI §11).
 *
 * Seeder ini juga menautkan `users.department_id` berdasarkan teks lama
 * `users.department`, sehingga data yang sudah ada langsung terbawa.
 * Seeder aman dijalankan berulang (idempoten).
 */
class DepartmentTeamSeeder extends Seeder
{
    /** @var list<array{name: string, code: string, description: string}> */
    private const DEPARTMENTS = [
        ['name' => 'Management',        'code' => 'MGT', 'description' => 'Manajemen dan pengambil keputusan.'],
        ['name' => 'Quality Assurance', 'code' => 'QA',  'description' => 'Penilaian mutu dan audit kualitas layanan.'],
        ['name' => 'Call Center',       'code' => 'CC',  'description' => 'Layanan pelanggan lini depan.'],
        ['name' => 'Information Technology', 'code' => 'IT', 'description' => 'Dukungan teknis dan sistem.'],
        ['name' => 'Human Resources',   'code' => 'HR',  'description' => 'Kepegawaian dan pengembangan SDM.'],
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $i => $row) {
            Department::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($row['name'])],
                $row + ['status' => 'active', 'sort_order' => $i],
            );
        }

        // Tautkan user yang punya teks `department` ke master divisi.
        $byName = Department::pluck('id', 'name');

        User::query()
            ->whereNull('department_id')
            ->whereNotNull('department')
            ->each(function (User $user) use ($byName) {
                $id = $byName[$user->department] ?? null;

                if ($id) {
                    $user->forceFill(['department_id' => $id])->save();
                }
            });

        // Tim awal: satu tim contoh per divisi operasional.
        $teamDefaults = [
            ['department' => 'Quality Assurance', 'name' => 'QA Core'],
            ['department' => 'Call Center',       'name' => 'CC Reguler'],
        ];

        foreach ($teamDefaults as $row) {
            Team::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($row['name'])],
                [
                    'department_id' => $byName[$row['department']] ?? null,
                    'name' => $row['name'],
                    'status' => 'active',
                ],
            );
        }
    }
}
