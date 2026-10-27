<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->nullable()->after('email');
            $table->string('department')->nullable()->after('employee_id');
            $table->string('phone')->nullable()->after('department');
            $table->string('avatar')->nullable()->after('phone');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('avatar');
            $table->timestamp('last_login')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_id', 'department', 'phone', 'avatar', 'status', 'last_login']);
        });
    }
};
