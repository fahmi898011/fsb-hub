<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('information_id')->constrained('information_items')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('information_versions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('viewed_at')->useCurrent();

            $table->index(['information_id', 'viewed_at']);
        });

        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('information_id')->constrained('information_items')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('information_versions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('downloaded_at')->useCurrent();

            $table->index(['information_id', 'downloaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downloads');
        Schema::dropIfExists('document_views');
    }
};
