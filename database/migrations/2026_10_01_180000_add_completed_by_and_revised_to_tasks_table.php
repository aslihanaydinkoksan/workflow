<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'completed_by')) {
                $table->foreignId('completed_by')
                    ->nullable()
                    ->after('assigned_role')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        // status sütununa 'revised' durumunu ekle
        try {
            DB::statement("ALTER TABLE `tasks` MODIFY COLUMN `status` ENUM('pending','in_progress','completed','rejected','skipped','cancelled','revised') NOT NULL DEFAULT 'pending'");
        } catch (\Throwable $e) {
            // Eğer enum değiştirilemezse varchar olarak genişlet
            DB::statement("ALTER TABLE `tasks` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'pending'");
        }

        // Geçmişte tamamlanmış/reddedilmiş görevlerde completed_by boş ise assigned_to ile doldur
        DB::statement("UPDATE `tasks` SET `completed_by` = `assigned_to` WHERE `completed_by` IS NULL AND `assigned_to` IS NOT NULL AND `status` != 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'completed_by')) {
                $table->dropForeign(['completed_by']);
                $table->dropColumn('completed_by');
            }
        });
    }
};
