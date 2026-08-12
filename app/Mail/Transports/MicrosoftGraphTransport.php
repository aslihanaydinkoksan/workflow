<?php

namespace App\Mail\Transports;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(
        protected string $tenantId,
        protected string $clientId,
        protected string $clientSecret,
        protected string $fromAddress
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        /** @var Email $email */
        $email = $message->getOriginalMessage();

        // 1. ADIM: Token Al (Lokal SSL sorunlarını aşmak için withoutVerifying)
        $tokenResponse = Http::withoutVerifying()->asForm()->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => 'https://graph.microsoft.com/.default',
            'grant_type'    => 'client_credentials',
        ]);

        $token = $tokenResponse->json('access_token');

        // 2. ADIM: Alıcıları Ayarla
        $toRecipients = [];
        foreach ($email->getTo() as $address) {
            $toRecipients[] = [
                'emailAddress' => ['address' => $address->getAddress()]
            ];
        }

        // 3. ADIM: İçeriği Ayarla (HTML mi düz metin mi?)
        $htmlBody = $email->getHtmlBody();
        $textBody = $email->getTextBody();

        $content = $htmlBody
            ? (is_resource($htmlBody) ? stream_get_contents($htmlBody) : $htmlBody)
            : (is_resource($textBody) ? stream_get_contents($textBody) : $textBody);

        $contentType = $htmlBody ? 'HTML' : 'Text';

        // 4. ADIM: Microsoft Graph API'ye Teslim Et
        Http::withoutVerifying()->withToken($token)
            ->post("https://graph.microsoft.com/v1.0/users/{$this->fromAddress}/sendMail", [
                'message' => [
                    'subject' => $email->getSubject(),
                    'body' => [
                        'contentType' => $contentType,
                        'content' => $content
                    ],
                    'toRecipients' => $toRecipients
                ],
                'saveToSentItems' => 'false' // Gönderilenlerde şişme yapmaması için false
            ]);
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
