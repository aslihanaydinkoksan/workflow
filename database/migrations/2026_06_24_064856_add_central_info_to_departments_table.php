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
            $table->unsignedBigInteger('central_id')->nullable()->after('id')->comment('Merkezi Sistem (SSO) ID');
            $table->string('manager_info')->nullable()->after('parent_id')->comment('Müdür / Müdür Yrd Bilgisi');
            $table->string('director_info')->nullable()->after('manager_info')->comment('Direktör Bilgisi');
            $table->boolean('is_synced')->default(false)->after('director_info')->comment('Merkezden mi geldi?');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['central_id', 'manager_info', 'director_info', 'is_synced']);
        });
    }
};
