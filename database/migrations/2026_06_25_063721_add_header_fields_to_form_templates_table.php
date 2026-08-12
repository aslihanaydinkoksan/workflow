<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_templates', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('id')->constrained('form_categories')->nullOnDelete();
            $table->string('document_no')->nullable()->after('name');
            $table->date('publish_date')->nullable()->after('document_no');
            $table->string('revision_no')->nullable()->after('publish_date');
            $table->date('revision_date')->nullable()->after('revision_no');
            $table->integer('page_no')->default(1)->after('revision_date');
        });
    }

    public function down(): void
    {
        Schema::table('form_templates', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn([
                'category_id',
                'document_no',
                'publish_date',
                'revision_no',
                'revision_date',
                'page_no'
            ]);
        });
    }
};
