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
        Schema::table('departments', function (Blueprint $table) {
            $table->unsignedBigInteger('manager_id')->nullable()->after('manager_info')->comment('Lokal Veritabanındaki Müdür (User ID)');
            $table->unsignedBigInteger('director_id')->nullable()->after('director_info')->comment('Lokal Veritabanındaki Direktör (User ID)');
            
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('director_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropForeign(['director_id']);
            $table->dropColumn(['manager_id', 'director_id']);
        });
    }
};
