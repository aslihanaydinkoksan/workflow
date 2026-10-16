<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tasks', 'deleted_at')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }

        if (!Schema::hasColumn('process_instances', 'deleted_at')) {
            Schema::table('process_instances', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'deleted_at')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('process_instances', 'deleted_at')) {
            Schema::table('process_instances', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
