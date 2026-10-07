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
        Schema::table('workflows', function (Blueprint $table) {
            if (!Schema::hasColumn('workflows', 'parent_workflow_id')) {
                $table->foreignId('parent_workflow_id')
                    ->nullable()
                    ->after('version')
                    ->constrained('workflows')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('workflows', 'is_latest')) {
                $table->boolean('is_latest')
                    ->default(true)
                    ->after('parent_workflow_id')
                    ->index();
            }

            if (!Schema::hasColumn('workflows', 'version_notes')) {
                $table->text('version_notes')
                    ->nullable()
                    ->after('is_latest');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            if (Schema::hasColumn('workflows', 'parent_workflow_id')) {
                $table->dropForeign(['parent_workflow_id']);
                $table->dropColumn('parent_workflow_id');
            }
            if (Schema::hasColumn('workflows', 'is_latest')) {
                $table->dropColumn('is_latest');
            }
            if (Schema::hasColumn('workflows', 'version_notes')) {
                $table->dropColumn('version_notes');
            }
        });
    }
};
