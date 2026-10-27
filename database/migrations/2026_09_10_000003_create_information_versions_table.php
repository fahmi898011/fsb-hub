<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('information_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('information_id')->constrained('information_items')->cascadeOnDelete();
            $table->string('version');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->longText('content')->nullable();
            $table->text('change_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('information_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('information_versions');
    }
};
