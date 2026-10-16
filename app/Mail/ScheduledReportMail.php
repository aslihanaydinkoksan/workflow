<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Models\ReportSchedule;
use App\Models\ReportDashboard;

class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public ReportSchedule $schedule;
    public ReportDashboard $dashboard;
    public array $widgets;
    public ?string $pdfContent;
    public ?string $excelContent;

    public function __construct(
        ReportSchedule $schedule,
        ReportDashboard $dashboard,
        array $widgets,
        ?string $pdfContent = null,
        ?string $excelContent = null
    ) {
        $this->schedule = $schedule;
        $this->dashboard = $dashboard;
        $this->widgets = $widgets;
        $this->pdfContent = $pdfContent;
        $this->excelContent = $excelContent;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📊 ' . $this->schedule->title . ' - Köksan BPM Raporu',
            using: [
                function ($message) {
                    $message->getHeaders()->addTextHeader('X-Mail-Event', 'scheduled_report');
                }
            ]
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.scheduled-report',
            with: [
                'schedule' => $this->schedule,
                'dashboard' => $this->dashboard,
                'widgets' => $this->widgets,
            ]
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->pdfContent) {
            $safeTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->dashboard->title);
            $attachments[] = Attachment::fromData(fn () => $this->pdfContent, $safeTitle . '_Raporu.pdf')
                ->withMime('application/pdf');
        }

        if ($this->excelContent) {
            $safeTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->dashboard->title);
            $attachments[] = Attachment::fromData(fn () => $this->excelContent, $safeTitle . '_Verileri.xlsx')
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }

        return $attachments;
    }
}
