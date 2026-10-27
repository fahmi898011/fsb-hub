<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_version_questions', function (Blueprint $table) {
            $table->json('options_snapshot')->nullable()->after('question_type_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_version_questions', function (Blueprint $table) {
            $table->dropColumn('options_snapshot');
        });
    }
};