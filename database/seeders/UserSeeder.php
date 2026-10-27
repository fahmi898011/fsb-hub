<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin FSB',
                'email' => 'admin@fsb.test',
                'employee_id' => 'ADM-001',
                'department' => 'Management',
                'role' => 'admin',
            ],
            [
                'name' => 'Rina QA',
                'email' => 'qa@fsb.test',
                'employee_id' => 'QA-001',
                'department' => 'Quality Assurance',
                'role' => 'qa',
            ],
            [
                'name' => 'Budi Karyawan',
                'email' => 'employee@fsb.test',
                'employee_id' => 'EMP-001',
                'department' => 'Call Center',
                'role' => 'employee',
            ],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt('password'),
                    'employee_id' => $data['employee_id'],
                    'department' => $data['department'],
                    'status' => 'active',
                ]
            );

            $user->syncRoles([$data['role']]);
        }
    }
}
