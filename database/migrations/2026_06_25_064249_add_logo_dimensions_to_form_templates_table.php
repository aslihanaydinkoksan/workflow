<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_templates', function (Blueprint $table) {
            $table->decimal('logo_width', 8, 2)->default(4.5)->after('page_no');
            $table->decimal('logo_height', 8, 2)->default(1.5)->after('logo_width');
        });
    }

    public function down(): void
    {
        Schema::table('form_templates', function (Blueprint $table) {
            $table->dropColumn(['logo_width', 'logo_height']);
        });
    }
};
