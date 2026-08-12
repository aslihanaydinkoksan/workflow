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
        Schema::table('tree_types', function (Blueprint $table) {
            // metadata için dinamik kuralları tutacak json kolonu
            $table->json('schema')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tree_types', function (Blueprint $table) {
            $table->dropColumn('schema');
        });
    }
};
