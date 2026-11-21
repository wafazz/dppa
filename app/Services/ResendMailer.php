<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Sends email through the Resend HTTP API (POST https://api.resend.com/emails).
 *
 * The uploaded logo is attached inline, so it shows in every mail client without a public web address.
 * Templates refer to it as cid:email-logo.
 */
class ResendMailer
{
    public const ENDPOINT = 'https://api.resend.com/emails';

    public const LOGO_CID = 'email-logo';

    public function __construct(
        private IntegrationSettings $settings,
        private Branding $branding,
    ) {}

    /**
     * @return string The Resend message id.
     *
     * @throws RuntimeException When Resend is not configured or rejects the request.
     */
    public function send(string $to, string $subject, string $html): string
    {
        $apiKey = $this->settings->get('resend.api_key');
        $fromEmail = $this->settings->get('resend.from_email');

        if ($apiKey === null || $fromEmail === null) {
            throw new RuntimeException(__('app.settings.resend_not_configured'));
        }

        $fromName = $this->settings->get('resend.from_name');

        $payload = [
            'from' => $fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail,
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
        ];

        $attachments = $this->inlineLogo($html);

        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(15)
            ->post(self::ENDPOINT, $payload);

        if ($response->failed()) {
            throw new RuntimeException(__('app.settings.send_failed', [
                'message' => $response->json('message') ?? (string) $response->status(),
            ]));
        }

        return (string) ($response->json('id') ?? '');
    }

    /**
     * The logo as an inline attachment, when the message refers to it and a logo has been uploaded.
     *
     * @return array<int, array{filename: string, content: string, content_id: string}>
     */
    private function inlineLogo(string $html): array
    {
        if (! str_contains($html, 'cid:'.self::LOGO_CID) || $this->branding->logoUrl() === null) {
            return [];
        }

        return [[
            'filename' => 'logo.png',
            'content' => base64_encode(Storage::disk(Branding::DISK)->get(Branding::LOGO_FILE)),
            'content_id' => self::LOGO_CID,
        ]];
    }
}
