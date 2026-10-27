<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anggota tim (team_members) — PRD KPI §11.
 *
 * Satu user hanya boleh berada di satu tim (`unique` pada `user_id`), agar
 * laporan tim tidak ambigu. `role` menandai apakah orang tersebut leader atau
 * anggota biasa di tim tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['leader', 'member'])->default('member');
            $table->timestamps();

            $table->unique('user_id');
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
