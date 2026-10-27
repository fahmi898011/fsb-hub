<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('information_tag', function (Blueprint $table) {
            $table->foreignId('information_id')->constrained('information_items')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['information_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('information_tag');
        Schema::dropIfExists('tags');
    }
};
