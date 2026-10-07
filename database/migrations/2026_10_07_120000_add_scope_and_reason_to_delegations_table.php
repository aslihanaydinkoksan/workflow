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
        Schema::table('delegations', function (Blueprint $table) {
            $table->string('scope_type', 30)->default('all')->after('end_date'); // all, category, workflow
            $table->string('scope_value', 100)->nullable()->after('scope_type');
            $table->string('reason', 255)->nullable()->after('scope_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delegations', function (Blueprint $table) {
            $table->dropColumn(['scope_type', 'scope_value', 'reason']);
        });
    }
};
