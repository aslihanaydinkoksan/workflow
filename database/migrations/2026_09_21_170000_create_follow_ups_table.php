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
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_instance_id')->constrained('process_instances')->cascadeOnDelete();
            $table->string('follow_up_type')->default('sample_conversion');
            $table->enum('status', ['pending', 'converted', 'not_converted', 'cancelled'])->default('pending');
            $table->dateTime('scheduled_at');
            $table->dateTime('last_reminded_at')->nullable();
            $table->integer('reminder_count')->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('response_status')->nullable();
            $table->string('order_number')->nullable();
            $table->text('non_conversion_reason')->nullable();
            $table->text('customer_feedback')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            
            // SAP S/4HANA Entegrasyon Alanları
            $table->string('sap_sales_order_id')->nullable();
            $table->enum('sap_sync_status', ['not_applicable', 'pending', 'synced', 'failed'])->default('not_applicable');
            $table->dateTime('sap_synced_at')->nullable();
            $table->json('sap_payload')->nullable();
            $table->json('sap_response')->nullable();
            
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
