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
        Schema::create('node_closures', function (Blueprint $table) {
            $table->foreignId('ancestor_id')->constrained('nodes')->cascadeOnDelete();
            $table->foreignId('descendant_id')->constrained('nodes')->cascadeOnDelete();
            $table->unsignedInteger('depth');

            // Composite Primary Key (Surrogate key kullanılmıyor)
            $table->primary(['ancestor_id', 'descendant_id']);
            
            // Performans Kritik İndeksler
            // Not: Composite PK'nın ilk kolonu ancestor_id olduğu için veritabanı motoru 
            // ancestor_id'yi zaten B-Tree left-prefix olarak indeksler, ancak talebinize 
            // istinaden açıkça TEK BAŞINA indeks olarak da eklenmiştir.
            $table->index('ancestor_id');
            $table->index('descendant_id');
            $table->index('depth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('node_closures');
    }
};
