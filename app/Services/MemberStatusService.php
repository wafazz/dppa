<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberStatusLog;
use App\Models\PositionAppointment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes an application status, assigns member numbers on approval and writes the audit log.
 */
class MemberStatusService
{
    public function __construct(
        private MemberNumberGenerator $memberNumbers,
        private EmailNotices $notices,
        private InvoiceService $invoices,
    ) {}

    /**
     * Move a member to a new status inside one transaction.
     *
     * @throws ValidationException When the member already has the requested status.
     */
    public function changeStatus(Member $member, MemberStatus $newStatus, User $actor, ?string $note): Member
    {
        $changed = $this->applyStatus($member, $newStatus, $actor, $note);

        // Approval gives the member a login account. A clash with an existing e-mail is left for an administrator.
        if ($newStatus === MemberStatus::Approved) {
            try {
                app(MemberAccountService::class)->ensureAccount($changed);
            } catch (ValidationException) {
                // Reported on the member page when the set-up link is requested.
            }
        }

        if ($newStatus === MemberStatus::Approved) {
            $this->invoices->issueOnApproval($changed);
        }

        $this->notifyDecision($changed, $newStatus);

        return $changed;
    }

    /**
     * Tells the applicant about an approval (with the member number) or a rejection.
     */
    private function notifyDecision(Member $member, MemberStatus $status): void
    {
        if ($status === MemberStatus::Approved) {
            $this->notices->deliver('application_approved', (string) $member->email, [
                'name' => $member->applicant_name,
                'business' => $member->business_name,
                'member_no' => (string) $member->member_no,
            ]);
        }

        if ($status === MemberStatus::Rejected) {
            $this->notices->deliver('application_rejected', (string) $member->email, [
                'name' => $member->applicant_name,
                'business' => $member->business_name,
            ]);
        }
    }

    private function applyStatus(Member $member, MemberStatus $newStatus, User $actor, ?string $note): Member
    {
        return DB::transaction(function () use ($member, $newStatus, $actor, $note): Member {
            $locked = Member::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $previousStatus = $locked->status;

            if ($previousStatus === $newStatus) {
                throw ValidationException::withMessages([
                    'status' => __('app.validation.status_already', ['status' => $newStatus->label()]),
                ]);
            }

            $now = now();
            $locked->status = $newStatus;

            match ($newStatus) {
                MemberStatus::Approved => $this->markApproved($locked, $now->year, $now),
                MemberStatus::Rejected => $this->markRejected($locked, $now),
                MemberStatus::Pending => $this->markPending($locked),
            };

            $locked->save();

            if ($newStatus !== MemberStatus::Approved) {
                $this->endCurrentAppointments($locked);
            }

            MemberStatusLog::query()->create([
                'member_id' => $locked->id,
                'from_status' => $previousStatus,
                'to_status' => $newStatus,
                'changed_by_user_id' => $actor->id,
                'note' => $note,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * A member who is no longer approved cannot hold a chamber position now. Current terms are ended, never deleted.
     */
    private function endCurrentAppointments(Member $member): void
    {
        $today = now()->toDateString();

        PositionAppointment::query()
            ->where('member_id', $member->id)
            ->where('is_current', true)
            ->get()
            ->each(function (PositionAppointment $appointment) use ($today): void {
                $appointment->is_current = false;
                $appointment->term_end = $appointment->term_end ?? $today;
                $appointment->save();
            });
    }

    /**
     * Record approval and keep an existing member number if one was already issued.
     */
    private function markApproved(Member $member, int $year, \DateTimeInterface $now): void
    {
        if ($member->member_no === null) {
            $member->member_no = $this->memberNumbers->generate($year);
        }

        $member->approved_at = $now;
        $member->rejected_at = null;
    }

    /**
     * Record rejection. The member number, if any, is never reused.
     */
    private function markRejected(Member $member, \DateTimeInterface $now): void
    {
        $member->rejected_at = $now;
        $member->approved_at = null;
    }

    /**
     * Return the application to the pending queue.
     */
    private function markPending(Member $member): void
    {
        $member->approved_at = null;
        $member->rejected_at = null;
    }
}
