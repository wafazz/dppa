<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the automatic e-mails. A failed or unconnected send is logged and reported as false, so the action that
 * triggered it (an approval, a sign-in, a scheduled reminder) still completes.
 */
class EmailNotices
{
    public function __construct(
        private ResendMailer $mailer,
        private EmailTemplate $templates,
        private IntegrationSettings $settings,
    ) {}

    /**
     * @param  array<string, scalar>  $values
     * @param  array<int, string>  $extraLines
     */
    public function deliver(
        string $type,
        string $to,
        array $values,
        ?string $actionUrl = null,
        array $extraLines = [],
        ?string $locale = null,
    ): bool {
        if (! $this->settings->isConnected('resend') || $to === '') {
            return false;
        }

        $message = $this->templates->compose($type, $values, $actionUrl, $locale, $extraLines);

        try {
            $this->mailer->send($to, $message['subject'], $message['html']);
        } catch (Throwable $exception) {
            Log::warning('Automatic e-mail was not sent.', ['type' => $type, 'reason' => $exception->getMessage()]);

            return false;
        }

        return true;
    }
}
