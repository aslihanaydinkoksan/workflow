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
        Schema::create('workflow_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Insert some default categories
        DB::table('workflow_categories')->insert([
            ['name' => 'İnsan Kaynakları', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Bilgi İşlem (IT)', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Finans ve Muhasebe', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'İdari İşler', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Satın Alma', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hukuk', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_categories');
    }
};
