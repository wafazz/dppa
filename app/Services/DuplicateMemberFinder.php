<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\Member;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds existing records that clash with a new or edited membership application.
 */
class DuplicateMemberFinder
{
    /**
     * Find an active application (not rejected) that already uses this SSM number.
     */
    public function ssmConflict(?string $ssmNo, ?int $ignoreMemberId = null): ?Member
    {
        $value = strtoupper(trim((string) $ssmNo));

        if ($value === '') {
            return null;
        }

        return Member::query()
            ->where('ssm_no', $value)
            ->where('status', '!=', MemberStatus::Rejected->value)
            ->when($ignoreMemberId, fn ($query, int $memberId) => $query->whereKeyNot($memberId))
            ->first();
    }

    /**
     * List other records that share an email, phone number or SSM number, for committee review.
     *
     * @return Collection<int, Member>
     */
    public function possibleDuplicates(Member $member): Collection
    {
        return Member::query()
            ->whereKeyNot($member->id)
            ->where(function ($query) use ($member): void {
                $query->where('email', $member->email)
                    ->orWhere('phone', $member->phone);

                if ($member->ssm_no !== null) {
                    $query->orWhere('ssm_no', $member->ssm_no);
                }
            })
            ->orderBy('id')
            ->limit(20)
            ->get();
    }
}
