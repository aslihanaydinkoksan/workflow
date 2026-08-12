<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * * MySQL üzerinde ENUM kolonuna 'rejected' değerini güvenli bir şekilde ekler.
     */
    public function up(): void
    {
        if (Schema::hasTable('process_instances')) {
            DB::statement("
                ALTER TABLE process_instances 
                MODIFY COLUMN status ENUM('running', 'waiting', 'completed', 'cancelled', 'rejected') 
                NOT NULL 
                DEFAULT 'running'
            ");
        }
    }

    /**
     * Reverse the migrations.
     * * İşlemi geri alırken veri kaybını önlemek için önce 'rejected' olan kayıtları 
     * 'cancelled' durumuna çeker, ardından ENUM listesinden 'rejected' değerini siler.
     */
    public function down(): void
    {
        if (Schema::hasTable('process_instances')) {
            // Veritabanı bütünlüğünü (Data Integrity) korumak için koruma kalkanı
            DB::table('process_instances')
                ->where('status', 'rejected')
                ->update(['status' => 'cancelled']);

            DB::statement("
                ALTER TABLE process_instances 
                MODIFY COLUMN status ENUM('running', 'waiting', 'completed', 'cancelled') 
                NOT NULL 
                DEFAULT 'running'
            ");
        }
    }
};
