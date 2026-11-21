<?php

namespace App\Services;

use App\Models\AccountPosition;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteBallot;
use App\Models\VoteOption;
use App\Models\VoteReceipt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets up and runs votes. A vote's choices are secret: the receipt says who voted, the ballot says what was chosen,
 * and the two are never stored together.
 */
class VoteService
{
    public function __construct(private ActivityLogger $activity) {}

    /**
     * Progress (turnout) and results are for management and for the person who set the vote up.
     */
    public function canViewProgress(User $viewer, Vote $vote): bool
    {
        return $viewer->isManagement() || $vote->created_by_user_id === $viewer->id;
    }

    public function canManage(User $actor, Vote $vote): bool
    {
        return $actor->isManagement() || $vote->created_by_user_id === $actor->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Vote
    {
        if (! $actor->canCreateVotes()) {
            throw new AuthorizationException(__('app.votes.error_cannot_create'));
        }

        $positionIds = $this->validPositionIds($data['account_position_ids'] ?? []);
        $voterIds = $this->resolveVoterIds($data['voter_ids'] ?? [], $positionIds);

        return DB::transaction(function () use ($actor, $data, $voterIds, $positionIds): Vote {
            $vote = Vote::query()->create([
                'title' => trim((string) $data['title']),
                'description' => $this->clean($data['description'] ?? null),
                'created_by_user_id' => $actor->id,
                'opens_at' => $this->time($data['opens_at'] ?? null),
                'closes_at' => $this->time($data['closes_at']),
            ]);

            $this->replaceOptions($vote, $data['options']);
            $vote->voters()->sync($voterIds);
            $vote->accountPositions()->sync($positionIds);

            $this->activity->record('vote.voters_set', null, $actor, $vote, [
                'voters' => count($voterIds),
                'account_positions' => $positionIds,
            ]);

            return $vote->load('options');
        });
    }

    /**
     * Details, options and voters can change only before anyone has voted.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Vote $vote, array $data): Vote
    {
        $this->assertCanManage($actor, $vote);

        if ($vote->status() === Vote::STATUS_CLOSED || $vote->status() === Vote::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['vote' => __('app.votes.error_not_editable')]);
        }

        if (VoteReceipt::query()->where('vote_id', $vote->id)->exists()) {
            throw ValidationException::withMessages(['vote' => __('app.votes.error_has_votes')]);
        }

        $positionIds = $this->validPositionIds($data['account_position_ids'] ?? []);
        $voterIds = $this->resolveVoterIds($data['voter_ids'] ?? [], $positionIds);

        return DB::transaction(function () use ($vote, $data, $voterIds, $positionIds, $actor): Vote {
            $vote->forceFill([
                'title' => trim((string) $data['title']),
                'description' => $this->clean($data['description'] ?? null),
                'opens_at' => $this->time($data['opens_at'] ?? null),
                'closes_at' => $this->time($data['closes_at']),
            ])->save();

            $this->replaceOptions($vote, $data['options']);
            $vote->voters()->sync($voterIds);
            $vote->accountPositions()->sync($positionIds);

            $this->activity->record('vote.voters_set', null, $actor, $vote, [
                'voters' => count($voterIds),
                'account_positions' => $positionIds,
            ]);

            return $vote->load('options');
        });
    }

    /**
     * Ends an open vote now. The change is recorded in the activity log, which is when results become visible.
     */
    public function close(User $actor, Vote $vote): Vote
    {
        $this->assertCanManage($actor, $vote);

        if ($vote->status() !== Vote::STATUS_OPEN) {
            throw ValidationException::withMessages(['vote' => __('app.votes.error_not_open')]);
        }

        $vote->forceFill(['closed_at' => now()])->save();

        return $vote->refresh();
    }

    public function cancel(User $actor, Vote $vote): Vote
    {
        $this->assertCanManage($actor, $vote);

        if (! in_array($vote->status(), [Vote::STATUS_SCHEDULED, Vote::STATUS_OPEN], true)) {
            throw ValidationException::withMessages(['vote' => __('app.votes.error_not_cancellable')]);
        }

        $vote->forceFill(['cancelled_at' => now()])->save();

        return $vote->refresh();
    }

    /**
     * Records one voter's choice. The voter is checked against the eligible list, and a second attempt is refused.
     */
    public function cast(User $voter, Vote $vote, int $optionId): void
    {
        DB::transaction(function () use ($voter, $vote, $optionId): void {
            $locked = Vote::query()->whereKey($vote->id)->lockForUpdate()->firstOrFail();

            if ($locked->status() !== Vote::STATUS_OPEN) {
                throw ValidationException::withMessages(['option_id' => __('app.votes.error_not_open')]);
            }

            if (! $locked->voters()->whereKey($voter->id)->exists()) {
                throw ValidationException::withMessages(['option_id' => __('app.votes.error_not_eligible')]);
            }

            if (VoteReceipt::query()->where('vote_id', $locked->id)->where('user_id', $voter->id)->exists()) {
                throw ValidationException::withMessages(['option_id' => __('app.votes.error_already_voted')]);
            }

            $option = VoteOption::query()->where('vote_id', $locked->id)->whereKey($optionId)->first();

            if ($option === null) {
                throw ValidationException::withMessages(['option_id' => __('app.votes.error_invalid_option')]);
            }

            VoteReceipt::query()->create(['vote_id' => $locked->id, 'user_id' => $voter->id, 'cast_at' => now()]);
            VoteBallot::query()->create(['vote_id' => $locked->id, 'vote_option_id' => $option->id]);

            // The trail says the person voted. It never says which option.
            $this->activity->record('vote.cast', null, $voter, $locked);
        });
    }

    /**
     * Counts for a closed vote. Before it closes, only the turnout is shown.
     *
     * @return array{turnout: int, eligible: int, closed: bool, options: ?array<int, array{id: int, label: string, votes: int}>, total: ?int}
     */
    public function results(Vote $vote): array
    {
        $turnout = VoteReceipt::query()->where('vote_id', $vote->id)->count();
        $eligible = $vote->voters()->count();
        $closed = $vote->status() === Vote::STATUS_CLOSED;

        if (! $closed) {
            return ['turnout' => $turnout, 'eligible' => $eligible, 'closed' => false, 'options' => null, 'total' => null];
        }

        $counts = VoteBallot::query()
            ->where('vote_id', $vote->id)
            ->selectRaw('vote_option_id, COUNT(*) as total')
            ->groupBy('vote_option_id')
            ->pluck('total', 'vote_option_id');

        $options = $vote->options->map(fn (VoteOption $option): array => [
            'id' => $option->id,
            'label' => $option->label,
            'votes' => (int) ($counts[$option->id] ?? 0),
        ])->all();

        return [
            'turnout' => $turnout,
            'eligible' => $eligible,
            'closed' => true,
            'options' => $options,
            'total' => array_sum(array_column($options, 'votes')),
        ];
    }

    private function assertCanManage(User $actor, Vote $vote): void
    {
        if (! $this->canManage($actor, $vote)) {
            throw new AuthorizationException(__('app.votes.error_not_yours'));
        }
    }

    /**
     * @param  array<int, mixed>  $options
     */
    private function replaceOptions(Vote $vote, array $options): void
    {
        $vote->options()->delete();

        foreach (array_values($options) as $position => $label) {
            VoteOption::query()->create([
                'vote_id' => $vote->id,
                'label' => trim((string) $label),
                'position' => $position,
            ]);
        }
    }

    /**
     * The management positions chosen, limited to positions that exist.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function validPositionIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return AccountPosition::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Everyone picked by name plus everyone holding a chosen management position, at the moment the vote is set up.
     *
     * @param  array<int, mixed>  $voterIds
     * @param  array<int, int>  $positionIds
     * @return array<int, int>
     *
     * @throws ValidationException When nobody at all is eligible.
     */
    private function resolveVoterIds(array $voterIds, array $positionIds): array
    {
        $holders = $positionIds === []
            ? []
            : User::query()->where('is_active', true)->whereIn('account_position_id', $positionIds)->pluck('id')->all();

        $ids = $this->validVoterIds(array_merge($voterIds, $holders));

        if ($ids === []) {
            throw ValidationException::withMessages(['voter_ids' => __('app.votes.error_no_voters')]);
        }

        return $ids;
    }

    /**
     * Any active account can be made eligible, including the account setting the vote up.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     *
     * @throws ValidationException
     */
    private function validVoterIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $valid = User::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($valid) !== count($ids)) {
            throw ValidationException::withMessages(['voter_ids' => __('app.votes.error_voter_invalid')]);
        }

        return $valid;
    }

    private function time(?string $value): ?Carbon
    {
        return $value === null || $value === '' ? null : Carbon::parse($value, config('app.timezone'));
    }

    private function clean(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
