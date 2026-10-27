<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            UserSeeder::class,
            DepartmentTeamSeeder::class,
            CategorySeeder::class,
            InformationSeeder::class,
            ChatSeeder::class,
            // Master KPI/QA Scorecard (PRD Employee KPI/QA §3.1).
            // AGENT: asisten | 2026-10-02 | daftarkan ScorecardSeeder | modul KPI, fase 1 master
            ScorecardSeeder::class,
        ]);
    }
}
