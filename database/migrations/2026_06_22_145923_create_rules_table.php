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
        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('node_id');          // hangi workflow node'u için
            $table->string('name');
            $table->integer('priority')->default(0);  // yüksek = önce değerlendirilir
            $table->enum('condition_type', ['all', 'any']); // AND = all, OR = any
            $table->json('conditions');         // [{ field, operator, value }]
            $table->json('action');             // { type, params }
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        
            $table->index(['workflow_id', 'node_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rules');
    }
};
