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
        if (!Schema::hasColumn('form_templates', 'paper_size')) {
            Schema::table('form_templates', function (Blueprint $table) {
                $table->string('paper_size', 10)->default('a4')->after('page_no');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('form_templates', 'paper_size')) {
            Schema::table('form_templates', function (Blueprint $table) {
                $table->dropColumn('paper_size');
            });
        }
    }
};
