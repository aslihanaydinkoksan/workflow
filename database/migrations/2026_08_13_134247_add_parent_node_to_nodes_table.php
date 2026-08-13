<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('nodes', function (Blueprint $table) {
        // parent_node kolonunu ekliyoruz
        $table->json('parent_node')->nullable()->after('label');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down()
{
    Schema::table('nodes', function (Blueprint $table) {
        $table->dropColumn('parent_node');
    });
}
};
