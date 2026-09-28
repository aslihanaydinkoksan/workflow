<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_event_id')->constrained('mail_events')->cascadeOnDelete();
            $table->json('target_users')->nullable(); // array of user IDs
            $table->json('target_roles')->nullable(); // array of role names / IDs
            $table->json('target_departments')->nullable(); // array of department IDs
            $table->json('allowed_days')->nullable(); // array e.g. ['Monday', 'Tuesday']
            $table->time('allowed_time_start')->nullable(); // e.g. '08:00'
            $table->time('allowed_time_end')->nullable(); // e.g. '18:00'
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_rules');
    }
};
