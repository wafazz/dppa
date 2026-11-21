<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

/**
 * Bulk announcements. Kept apart from the automatic e-mails: a member receives one only after agreeing to
 * announcements at registration, with a verified address, and every message carries a one-click way to stop.
 */
class AnnouncementService
{
    public function __construct(
        private ResendMailer $mailer,
        private EmailTemplate $templates,
        private IntegrationSettings $settings,
        private ActivityLogger $activity,
    ) {}

    /**
     * Approved members who agreed to announcements and whose address is verified.
     *
     * @return Builder<Member>
     */
    public function recipients(): Builder
    {
        return Member::query()
            ->where('status', MemberStatus::Approved)
            ->whereNotNull('announcement_consent_at')
            ->whereNotNull('email_verified_at')
            ->whereNotNull('email');
    }

    /**
     * @return array{sent: int, failed: int}
     *
     * @throws RuntimeException When Resend is not connected.
     */
    public function send(User $actor, string $subject, string $body): array
    {
        if (! $this->settings->isConnected('resend')) {
            throw new RuntimeException(__('app.settings.resend_not_configured'));
        }

        $paragraphs = preg_split('/\R\s*\R/', trim($body)) ?: [];
        $sent = 0;
        $failed = 0;

        $this->recipients()->each(function (Member $member) use ($subject, $paragraphs, &$sent, &$failed): void {
            $unsubscribe = URL::signedRoute('announcements.unsubscribe', ['member' => $member->id]);
            $html = $this->templates->html($paragraphs, __('app.announcements.unsubscribe'), $unsubscribe);

            try {
                $this->mailer->send((string) $member->email, $subject, $html);
                $sent++;
            } catch (Throwable) {
                $failed++;
            }
        });

        $this->activity->record('announcement.sent', $subject, $actor, null, ['sent' => $sent, 'failed' => $failed]);

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Stops announcements for a member. Their other e-mails are not affected.
     */
    public function unsubscribe(Member $member): void
    {
        $member->forceFill(['announcement_consent_at' => null])->save();
    }
}
