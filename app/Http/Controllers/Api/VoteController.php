<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\VoteRequest;
use App\Models\AccountPosition;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteReceipt;
use App\Services\VoteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VoteController extends Controller
{
    /**
     * Every vote is listed for every account. Progress and results are added only where the account may see them.
     */
    public function index(Request $request, VoteService $votes): JsonResponse
    {
        $viewer = $request->user();

        $list = Vote::query()
            ->with(['options', 'creator:id,name', 'accountPositions:id,name_bm'])
            ->withCount('voters')
            ->orderByDesc('closes_at')
            ->limit(200)
            ->get()
            ->map(fn (Vote $vote): array => $this->payload($vote, $viewer, $votes));

        return response()->json(['data' => $list]);
    }

    public function store(VoteRequest $request, VoteService $votes): JsonResponse
    {
        try {
            $vote = $votes->create($request->user(), $request->validated());
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json(['data' => $this->payload($vote->load(['options', 'creator:id,name']), $request->user(), $votes)], 201);
    }

    public function update(VoteRequest $request, Vote $vote, VoteService $votes): JsonResponse
    {
        $updated = $votes->update($request->user(), $vote, $request->validated());

        return response()->json(['data' => $this->payload($updated->load(['options', 'creator:id,name']), $request->user(), $votes)]);
    }

    public function close(Request $request, Vote $vote, VoteService $votes): JsonResponse
    {
        $closed = $votes->close($request->user(), $vote);

        return response()->json(['data' => $this->payload($closed->load(['options', 'creator:id,name']), $request->user(), $votes)]);
    }

    public function cancel(Request $request, Vote $vote, VoteService $votes): JsonResponse
    {
        $cancelled = $votes->cancel($request->user(), $vote);

        return response()->json(['data' => $this->payload($cancelled->load(['options', 'creator:id,name']), $request->user(), $votes)]);
    }

    public function cast(Request $request, Vote $vote, VoteService $votes): JsonResponse
    {
        $validated = $request->validate(['option_id' => ['required', 'integer']]);

        $votes->cast($request->user(), $vote, (int) $validated['option_id']);

        return response()->json(['data' => $this->payload($vote->fresh(['options', 'creator:id,name']), $request->user(), $votes)]);
    }

    /**
     * Accounts that can be picked as voters, and accounts that may be allowed to create votes.
     */
    public function voters(): JsonResponse
    {
        $accounts = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'account_type']);

        return response()->json(['data' => $accounts->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'account_type_label' => $user->account_type->label(),
        ])]);
    }

    public function creators(): JsonResponse
    {
        $accounts = User::query()
            ->where('is_active', true)
            ->where('account_type', '!=', AccountType::Superadmin->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'account_type', 'can_create_votes']);

        return response()->json(['data' => $accounts->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'account_type_label' => $user->account_type->label(),
            'can_create_votes' => (bool) $user->can_create_votes,
            'always_allowed' => $user->isManagement(),
        ])]);
    }

    public function setCreator(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate(['can_create_votes' => ['required', 'boolean']]);

        if ($user->isManagement() || $user->isSuperadmin()) {
            throw ValidationException::withMessages(['can_create_votes' => __('app.votes.error_creator_always')]);
        }

        $user->forceFill(['can_create_votes' => $validated['can_create_votes']])->save();

        return response()->json(['data' => [
            'id' => $user->id,
            'can_create_votes' => (bool) $user->can_create_votes,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Vote $vote, User $viewer, VoteService $votes): array
    {
        $status = $vote->status();
        $isEligible = $vote->voters()->whereKey($viewer->id)->exists();
        $hasVoted = VoteReceipt::query()->where('vote_id', $vote->id)->where('user_id', $viewer->id)->exists();

        return [
            'id' => $vote->id,
            'title' => $vote->title,
            'description' => $vote->description,
            'status' => $status,
            'status_label' => __('app.votes.status.'.$status),
            'opens_at' => $vote->opens_at?->toIso8601String(),
            'closes_at' => $vote->closes_at->toIso8601String(),
            'closed_at' => $vote->closed_at?->toIso8601String(),
            'cancelled_at' => $vote->cancelled_at?->toIso8601String(),
            'creator_name' => $vote->creator?->name,
            'can_manage' => $votes->canManage($viewer, $vote) && in_array($status, [Vote::STATUS_SCHEDULED, Vote::STATUS_OPEN], true),
            'can_edit' => $votes->canManage($viewer, $vote) && $status === Vote::STATUS_SCHEDULED && ! VoteReceipt::query()->where('vote_id', $vote->id)->exists(),
            'is_eligible' => $isEligible,
            'has_voted' => $hasVoted,
            'can_vote' => $status === Vote::STATUS_OPEN && $isEligible && ! $hasVoted,
            'options' => $vote->options->map(fn ($option): array => ['id' => $option->id, 'label' => $option->label])->values(),
            'voter_ids' => $votes->canManage($viewer, $vote) ? $vote->voters()->pluck('users.id')->values() : [],
            'account_position_ids' => $vote->accountPositions->pluck('id')->values(),
            'account_positions' => $vote->accountPositions->map(fn ($position): array => ['id' => $position->id, 'name' => $position->name_bm])->values(),
            'progress_visible' => $votes->canViewProgress($viewer, $vote),
            // Results are public once a vote closes. Before that, only management and the creator see progress.
            'results' => $votes->canViewProgress($viewer, $vote) || $status === Vote::STATUS_CLOSED ? $votes->results($vote) : null,
        ];
    }

    /**
     * Management positions that a vote can be given to.
     */
    public function positions(): JsonResponse
    {
        $positions = AccountPosition::query()->where('is_active', true)->orderBy('level')->get(['id', 'name_bm', 'level']);

        return response()->json(['data' => $positions->map(fn (AccountPosition $position): array => [
            'id' => $position->id,
            'name' => $position->name_bm,
            'level' => $position->level,
        ])]);
    }
}
