<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Task $task)
    {
        $this->task->loadMissing(['processInstance.workflow', 'assignedUser']);
    }

    public function envelope(): Envelope
    {
        // "Workflow 13" yerine veritabanındaki gerçek adını alıyoruz (Örn: Araç Talebi Süreci)
        $workflowName = $this->task->processInstance->workflow->name ?? 'İş Akışı Talebi';

        return new Envelope(
            subject: "Yeni Görev Ataması: {$workflowName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            // Markdown yerine tam kontrollü kurumsal HTML View kullanıyoruz
            view: 'emails.task-assigned',
        );
    }
}
