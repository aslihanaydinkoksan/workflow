<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\FollowUp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FollowUpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FollowUp $followUp,
        public ?string $customMessage = null
    ) {
        $this->followUp->loadMissing(['processInstance.workflow', 'processInstance.starter', 'assignedUser']);
    }

    public function envelope(): Envelope
    {
        $instanceId = $this->followUp->process_instance_id;
        $attempt = $this->followUp->reminder_count > 0 ? " (Hatırlatma #{$this->followUp->reminder_count})" : "";

        return new Envelope(
            subject: "Numune Takip & Sipariş Dönüşümü: #{$instanceId}{$attempt}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sample-follow-up',
        );
    }
}
