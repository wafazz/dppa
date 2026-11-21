<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Sends the link an account holder uses to choose a password. The link is e-mailed when Resend is
 * connected; otherwise it is returned once so an administrator can pass it on.
 */
class AccountSetupLinkService
{
    public function __construct(
        private ResendMailer $mailer,
        private EmailTemplate $templates,
        private IntegrationSettings $settings,
        private ActivityLogger $activity,
    ) {}

    /**
     * @return array{emailed: bool, url: ?string}
     */
    public function send(User $account, ?User $actor): array
    {
        $token = Password::broker()->createToken($account);
        $url = route('password.setup', ['token' => $token, 'email' => $account->email]);

        if ($this->settings->isConnected('resend')) {
            $message = $this->templates->compose('account_setup', ['name' => $account->name, 'hours' => 72], $url);

            $this->mailer->send($account->email, $message['subject'], $message['html']);

            $this->activity->record('account.setup_link_sent', 'emailed', $actor, $account, ['channel' => 'email']);

            return ['emailed' => true, 'url' => null];
        }

        $this->activity->record('account.setup_link_sent', 'shown to administrator', $actor, $account, ['channel' => 'manual']);

        return ['emailed' => false, 'url' => $url];
    }
}
