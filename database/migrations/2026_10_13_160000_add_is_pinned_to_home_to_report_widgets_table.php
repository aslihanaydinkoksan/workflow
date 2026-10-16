<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_widgets', function (Blueprint $table) {
            $table->boolean('is_pinned_to_home')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('report_widgets', function (Blueprint $table) {
            $table->dropColumn('is_pinned_to_home');
        });
    }
};
