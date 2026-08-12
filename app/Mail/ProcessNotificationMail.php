<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ProcessInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProcessNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProcessInstance $instance,
        public array $mailData = []
    ) {
        $this->instance->loadMissing('workflow');
    }

    public function envelope(): Envelope
    {
        $workflowName = $this->instance->workflow->name ?? 'İş Akışı Talebi';

        return new Envelope(
            subject: "Süreç Bilgilendirmesi: {$workflowName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.process-notification',
        );
    }
}
