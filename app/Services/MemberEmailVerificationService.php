<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Confirms that an applicant owns the e-mail address on the application. The link is signed and expires
 * after LINK_HOURS. It carries the address it was sent to, so an address changed later needs a new link.
 */
class MemberEmailVerificationService
{
    public const LINK_HOURS = 72;

    public function __construct(
        private ResendMailer $mailer,
        private EmailTemplate $templates,
        private ActivityLogger $activity,
    ) {}

    /**
     * Sends the verification link to the applicant. The reminder type is used by the scheduled reminder.
     *
     * @throws RuntimeException When Resend is not configured or rejects the message.
     */
    public function sendLink(Member $member, ?User $actor = null, string $type = 'member_verify'): void
    {
        $url = URL::temporarySignedRoute(
            'registration.verify-email',
            now()->addHours(self::LINK_HOURS),
            ['member' => $member->id, 'email' => $member->email],
        );

        $message = $this->templates->compose(
            $type,
            ['name' => $member->applicant_name, 'business' => $member->business_name, 'hours' => self::LINK_HOURS],
            $url,
        );

        $this->mailer->send($member->email, $message['subject'], $message['html']);

        $this->activity->record('member.verification_sent', $member->email, $actor, $member, ['channel' => 'email']);
    }

    /**
     * Marks the address as confirmed and tells the applicant. Returns false when it was already confirmed.
     */
    public function verify(Member $member): bool
    {
        if ($member->email_verified_at !== null) {
            return false;
        }

        $member->forceFill(['email_verified_at' => now()])->save();

        $this->activity->record('member.email_verified', $member->email, null, $member, ['channel' => 'email']);

        try {
            $message = $this->templates->compose('member_verified', [
                'name' => $member->applicant_name,
                'business' => $member->business_name,
            ]);

            $this->mailer->send($member->email, $message['subject'], $message['html']);
        } catch (RuntimeException $exception) {
            // The address is confirmed either way; the confirmation message is informational only.
            Log::warning('Membership confirmation e-mail was not sent.', ['member_id' => $member->id, 'reason' => $exception->getMessage()]);
        }

        return true;
    }
}
