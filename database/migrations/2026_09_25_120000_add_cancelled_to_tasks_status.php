<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * MySQL uzerinde tasks ENUM kolonuna 'cancelled' degerini ekler.
     */
    public function up(): void
    {
        if (Schema::hasTable('tasks')) {
            DB::statement("
                ALTER TABLE tasks 
                MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'rejected', 'skipped', 'cancelled') 
                NOT NULL 
                DEFAULT 'pending'
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tasks')) {
            DB::table('tasks')
                ->where('status', 'cancelled')
                ->update(['status' => 'skipped']);

            DB::statement("
                ALTER TABLE tasks 
                MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'rejected', 'skipped') 
                NOT NULL 
                DEFAULT 'pending'
            ");
        }
    }
};
