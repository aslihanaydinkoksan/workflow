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
            $table->unsignedBigInteger('assistant_manager_id')->nullable()->after('manager_id')->comment('Lokal Veritabanındaki Müdür Yrd (User ID)');
            $table->foreign('assistant_manager_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['assistant_manager_id']);
            $table->dropColumn('assistant_manager_id');
        });
    }
};
