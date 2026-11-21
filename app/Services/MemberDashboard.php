<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a member sees on their dashboard: their membership, positions, votes waiting for them and their own activity.
 */
class MemberDashboard
{
    private const WAITING_LIMIT = 5;

    public function __construct(
        private TwoFactorService $twoFactor,
        private Avatar $avatars,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $member = $user->member;

        $waitingQuery = $this->votesWaitingFor($user);

        return [
            'name' => $user->name,
            'member_no' => $member?->member_no,
            'status' => $member?->status?->label(),
            'status_value' => $member?->status?->value,
            'approved_at' => $member?->approved_at?->translatedFormat('j M Y'),
            'positions' => $member === null ? [] : $member->appointments()
                ->with('chamberPosition')
                ->where('is_current', true)
                ->get()
                ->map(fn ($appointment): array => [
                    'name' => (string) $appointment->chamberPosition?->name_bm,
                    'term_end' => $appointment->term_end?->translatedFormat('j M Y'),
                ])
                ->all(),
            'waiting_count' => (clone $waitingQuery)->count(),
            'waiting' => (clone $waitingQuery)
                ->orderBy('closes_at')
                ->limit(self::WAITING_LIMIT)
                ->get(['id', 'title', 'closes_at'])
                ->map(fn (Vote $vote): array => [
                    'id' => $vote->id,
                    'title' => $vote->title,
                    'closes_at' => $vote->closes_at->translatedFormat('j M Y, H:i'),
                ])
                ->all(),
            'results_count' => Vote::query()
                ->whereNull('cancelled_at')
                ->where(fn (Builder $query) => $query->whereNotNull('closed_at')->orWhere('closes_at', '<=', now()))
                ->whereHas('voters', fn (Builder $voters) => $voters->whereKey($user->id))
                ->count(),
            'two_factor_on' => $this->twoFactor->enabledMethods($user) !== [],
            'checklist' => [
                ['label' => 'avatar', 'done' => $this->avatars->url($user) !== null],
                ['label' => 'two_factor', 'done' => $this->twoFactor->enabledMethods($user) !== []],
                ['label' => 'business', 'done' => $user->businessStatus() !== 'draft'],
            ],
            'activity' => ActivityLog::query()
                ->visibleTo($user)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (ActivityLog $entry): array => [
                    'label' => $entry->label(),
                    'occurred_at' => $entry->occurred_at?->translatedFormat('j M Y, H:i'),
                ])
                ->all(),
        ];
    }

    /**
     * Open votes this account is eligible for and has not voted in yet.
     *
     * @return Builder<Vote>
     */
    private function votesWaitingFor(User $user): Builder
    {
        $now = now();

        return Vote::query()
            ->whereNull('closed_at')
            ->whereNull('cancelled_at')
            ->where('closes_at', '>', $now)
            ->where(fn (Builder $query) => $query->whereNull('opens_at')->orWhere('opens_at', '<=', $now))
            ->whereHas('voters', fn (Builder $voters) => $voters->whereKey($user->id))
            ->whereNotExists(fn ($receipts) => $receipts->from('vote_receipts')
                ->whereColumn('vote_receipts.vote_id', 'votes.id')
                ->where('vote_receipts.user_id', $user->id));
    }
}
