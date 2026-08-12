<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create Directorates Table
        if (!Schema::hasTable('directorates')) {
            Schema::create('directorates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('director_id')->nullable(); // Will reference users.id later
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Add columns to Departments
        if (!Schema::hasColumn('departments', 'directorate_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->foreignId('directorate_id')->nullable()->constrained('directorates')->nullOnDelete();
                $table->boolean('is_active')->default(true);
            });
        }

        // 3. Create Department Managers table
        if (!Schema::hasTable('department_managers')) {
            Schema::create('department_managers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
                $table->string('type')->default('manager'); // 'manager' or 'assistant'
                $table->timestamps();
            });
        }

        // 4. Update Users table
        if (!Schema::hasColumn('users', 'first_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('tc_no')->nullable();
                $table->foreignId('directorate_id')->nullable()->constrained('directorates')->nullOnDelete();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->boolean('is_active')->default(true);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'first_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['directorate_id']);
                $table->dropColumn(['first_name', 'last_name', 'tc_no', 'directorate_id', 'company_id', 'is_active']);
            });
        }

        Schema::dropIfExists('department_managers');

        if (Schema::hasColumn('departments', 'directorate_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropForeign(['directorate_id']);
                $table->dropColumn(['directorate_id', 'is_active']);
            });
        }

        Schema::dropIfExists('directorates');
    }
};
