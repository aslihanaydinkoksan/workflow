<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_digest_queues', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_email')->index();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_code')->index(); // termin_warning, termin_overdue_escalation, boundary_timer_escalate, etc.
            $table->string('subject');
            $table->text('body')->nullable();
            $table->json('metadata')->nullable(); // process_id, task_id, action_url, etc.
            $table->string('status')->default('pending')->index(); // pending, sent, failed, cancelled
            $table->timestamp('queued_at')->useCurrent();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_digest_queues');
    }
};
