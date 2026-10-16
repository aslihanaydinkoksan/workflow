<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class MorningDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection<\App\Models\MailDigestQueue> $items
     */
    public function __construct(
        public ?User $recipientUser,
        public string $recipientEmail,
        public Collection $items
    ) {}

    public function envelope(): Envelope
    {
        $today = now()->format('d.m.Y');
        $itemCount = $this->items->count();

        return new Envelope(
            subject: "☀️ [Köksan BPM] Sabah Yönetici Özeti ({$itemCount} Bekleyen İş) - {$today}",
            using: [
                function ($message) {
                    $message->getHeaders()->addTextHeader('X-Mail-Event', 'morning_digest');
                },
            ]
        );
    }

    public function content(): Content
    {
        $criticalCount = $this->items->filter(fn ($i) => in_array($i->event_code, ['termin_overdue_escalation', 'boundary_timer_escalate']))->count();
        $warningCount = $this->items->filter(fn ($i) => $i->event_code === 'termin_warning')->count();

        return new Content(
            view: 'emails.morning_digest',
            with: [
                'recipientName' => $this->recipientUser?->name ?? 'Yetkili',
                'recipientEmail' => $this->recipientEmail,
                'items' => $this->items,
                'criticalCount' => $criticalCount,
                'warningCount' => $warningCount,
            ]
        );
    }
}
