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
        Schema::create('termin_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_instance_id')->constrained('process_instances')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->string('previous_termin')->nullable();
            $table->string('new_termin');
            $table->date('new_target_date')->nullable()->index();
            $table->string('reason_category', 50)->default('other');
            $table->text('reason_note');
            $table->string('status', 30)->default('approved'); // approved, pending, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('termin_revisions');
    }
};
