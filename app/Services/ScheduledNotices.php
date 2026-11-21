<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\PositionAppointment;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteReceipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The e-mails that depend on the time: verification reminders, term endings, vote notices and the weekly
 * digest for management. Each one is marked as sent so a later run does not send it again.
 */
class ScheduledNotices
{
    public const TERM_WARNING_DAYS = 30;

    public const CLOSING_WARNING_HOURS = 24;

    public function __construct(
        private EmailNotices $notices,
        private MemberEmailVerificationService $verification,
        private IntegrationSettings $settings,
    ) {}

    /**
     * Sends everything that is due. Nothing is marked as sent while Resend is not connected, so the messages
     * go out once it is.
     *
     * @return array<string, int>
     */
    public function sendDue(): array
    {
        if (! $this->settings->isConnected('resend')) {
            return ['skipped' => 1];
        }

        return [
            'verification_reminders' => $this->verificationReminders(),
            'term_endings' => $this->termEndings(),
            'votes_opened' => $this->votesOpened(),
            'votes_closing' => $this->votesClosing(),
            'votes_results' => $this->votesResults(),
        ];
    }

    /**
     * The weekly digest of applications waiting for review, sent to the administrators.
     */
    public function managementDigest(): int
    {
        if (! $this->settings->isConnected('resend')) {
            return 0;
        }

        $pending = Member::query()
            ->where('status', MemberStatus::Pending)
            ->orderBy('applied_at')
            ->get(['applicant_name', 'business_name']);

        if ($pending->isEmpty()) {
            return 0;
        }

        $lines = $pending->take(30)
            ->map(fn (Member $member): string => '- '.$member->applicant_name.' ('.$member->business_name.')')
            ->all();

        if ($pending->count() > 30) {
            $lines[] = '- '.__('app.emails.digest_more', ['count' => $pending->count() - 30]);
        }

        $sent = 0;

        $this->managers()->each(function (User $manager) use ($pending, $lines, &$sent): void {
            $sent += (int) $this->notices->deliver(
                'management_digest',
                (string) $manager->email,
                ['count' => $pending->count()],
                route('admin.members.index', ['status' => 'pending']),
                $lines,
            );
        });

        return $sent;
    }

    /**
     * Applicants who have not confirmed their e-mail a day after applying get one reminder with a new link.
     */
    private function verificationReminders(): int
    {
        $sent = 0;

        Member::query()
            ->where('status', MemberStatus::Pending)
            ->whereNull('email_verified_at')
            ->whereNull('verification_reminded_at')
            ->where('applied_at', '<=', now()->subDay())
            ->get()
            ->each(function (Member $member) use (&$sent): void {
                try {
                    $this->verification->sendLink($member, null, 'member_verify_reminder');
                } catch (Throwable) {
                    return;
                }

                $member->forceFill(['verification_reminded_at' => now()])->save();
                $sent++;
            });

        return $sent;
    }

    /**
     * Current members whose term ends within the warning period get one reminder.
     */
    private function termEndings(): int
    {
        $today = now()->startOfDay();
        $sent = 0;

        PositionAppointment::query()
            ->with(['member', 'chamberPosition'])
            ->where('is_current', true)
            ->whereNotNull('term_end')
            ->whereNull('term_reminded_at')
            ->whereBetween('term_end', [$today->toDateString(), $today->copy()->addDays(self::TERM_WARNING_DAYS)->toDateString()])
            ->get()
            ->each(function (PositionAppointment $appointment) use (&$sent, $today): void {
                $member = $appointment->member;

                if ($member === null || $member->email === null) {
                    return;
                }

                $termEnd = Carbon::parse($appointment->term_end);

                $delivered = $this->notices->deliver('appointment_term_ending', (string) $member->email, [
                    'name' => $member->applicant_name,
                    'position' => (string) $appointment->chamberPosition?->name_bm,
                    'term_end' => $termEnd->translatedFormat('j M Y'),
                    'days' => (string) max(0, (int) $today->diffInDays($termEnd, false)),
                ]);

                if ($delivered) {
                    $appointment->forceFill(['term_reminded_at' => now()])->save();
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * Everyone eligible for a vote hears when it opens. Each vote is announced once.
     */
    private function votesOpened(): int
    {
        $sent = 0;

        Vote::query()
            ->whereNull('cancelled_at')
            ->whereNull('opened_notified_at')
            ->where('closes_at', '>', now())
            ->where(fn ($query) => $query->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->get()
            ->each(function (Vote $vote) use (&$sent): void {
                $vote->voters()->where('is_active', true)->whereNotNull('email')->each(function (User $voter) use ($vote, &$sent): void {
                    $sent += (int) $this->notices->deliver('vote_opened', (string) $voter->email, [
                        'name' => $voter->name,
                        'vote' => $vote->title,
                        'closes' => $vote->closes_at->translatedFormat('j M Y, H:i'),
                    ], route('admin.votes.index', ['status' => 'open']));
                });

                $vote->forceFill(['opened_notified_at' => now()])->save();
            });

        return $sent;
    }

    /**
     * Eligible voters who have not voted yet hear that the vote closes within a day.
     */
    private function votesClosing(): int
    {
        $sent = 0;

        Vote::query()
            ->whereNull('cancelled_at')
            ->whereNull('closing_notified_at')
            ->where('closes_at', '>', now())
            ->where('closes_at', '<=', now()->addHours(self::CLOSING_WARNING_HOURS))
            ->get()
            ->each(function (Vote $vote) use (&$sent): void {
                $voted = VoteReceipt::query()->where('vote_id', $vote->id)->pluck('user_id')->all();

                $vote->voters()
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->whereNotIn('users.id', $voted)
                    ->each(function (User $voter) use ($vote, &$sent): void {
                        $sent += (int) $this->notices->deliver('vote_closing', (string) $voter->email, [
                            'name' => $voter->name,
                            'vote' => $vote->title,
                            'closes' => $vote->closes_at->translatedFormat('j M Y, H:i'),
                            'hours' => (string) self::CLOSING_WARNING_HOURS,
                        ], route('admin.votes.index', ['status' => 'open']));
                    });

                $vote->forceFill(['closing_notified_at' => now()])->save();
            });

        return $sent;
    }

    /**
     * Results are public after a vote closes, so every active account with an e-mail hears when they are published.
     */
    private function votesResults(): int
    {
        $sent = 0;

        Vote::query()
            ->whereNull('cancelled_at')
            ->whereNull('results_notified_at')
            ->where('closes_at', '<=', now())
            ->get()
            ->each(function (Vote $vote) use (&$sent): void {
                User::query()
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->each(function (User $account) use ($vote, &$sent): void {
                        $sent += (int) $this->notices->deliver('vote_results', (string) $account->email, [
                            'name' => $account->name,
                            'vote' => $vote->title,
                        ], route('admin.votes.index', ['status' => 'closed']));
                    });

                $vote->forceFill(['results_notified_at' => now()])->save();
            });

        return $sent;
    }

    /**
     * @return Builder<User>
     */
    private function managers(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('account_type', [AccountType::Superadmin->value, AccountType::Admin->value])
            ->whereNotNull('email');
    }
}
