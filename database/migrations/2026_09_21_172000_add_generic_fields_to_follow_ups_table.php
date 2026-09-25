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
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->string('title')->nullable()->after('follow_up_type');
            $table->text('prompt')->nullable()->after('title');
            $table->foreignId('sub_form_id')->nullable()->after('prompt')->constrained('form_templates')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropForeign(['sub_form_id']);
            $table->dropColumn(['title', 'prompt', 'sub_form_id']);
        });
    }
};
