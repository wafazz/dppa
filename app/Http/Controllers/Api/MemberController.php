<?php

namespace App\Http\Controllers\Api;

use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\MemberFilterRequest;
use App\Http\Requests\ReviewMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Requests\UpgradeMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Services\AccountService;
use App\Services\DuplicateMemberFinder;
use App\Services\HtmlSanitizer;
use App\Services\MemberAccountService;
use App\Services\MemberApplicationService;
use App\Services\MemberEmailVerificationService;
use App\Services\MemberStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MemberController extends Controller
{
    /**
     * Paginated, searchable, sortable member list.
     */
    public function index(MemberFilterRequest $request): AnonymousResourceCollection
    {
        $members = Member::query()
            ->with(['category', 'account'])
            ->filter($request->filters())
            ->orderBy($request->sortColumn(), $request->sortDirection())
            ->orderBy('id')
            ->paginate($request->perPage());

        return MemberResource::collection($members);
    }

    /**
     * Options for the approved-member Select2 used when assigning positions.
     */
    public function options(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $like = '%'.Member::escapeLike($term).'%';

        $members = Member::query()
            ->where('status', MemberStatus::Approved->value)
            ->when($term !== '', fn ($query) => $query->where(function ($query) use ($like): void {
                $query->where('applicant_name', 'like', $like)
                    ->orWhere('business_name', 'like', $like)
                    ->orWhere('member_no', 'like', $like);
            }))
            ->orderBy('applicant_name')
            ->limit(30)
            ->get(['id', 'member_no', 'applicant_name', 'business_name']);

        return response()->json([
            'data' => $members->map(fn (Member $member): array => [
                'id' => $member->id,
                'text' => ($member->member_no ? $member->member_no.' - ' : '')
                    .$member->applicant_name.' ('.$member->business_name.')',
            ])->values(),
        ]);
    }

    /**
     * Member detail, including status history, appointments and possible duplicate records.
     */
    public function show(Member $member, DuplicateMemberFinder $duplicates): JsonResource
    {
        $member->load(['category', 'statusLogs.changedBy', 'appointments.chamberPosition', 'account']);

        return (new MemberResource($member))->additional([
            'possible_duplicates' => MemberResource::collection($duplicates->possibleDuplicates($member)),
        ]);
    }

    /**
     * Creates the member's login account if needed and sends the set-up link, or shows it once when e-mail is not connected.
     */
    public function setupLink(Request $request, Member $member, MemberAccountService $accounts): JsonResponse
    {
        if ($member->status !== MemberStatus::Approved) {
            return response()->json(['message' => __('app.members.account.only_approved')], 422);
        }

        try {
            $result = $accounts->sendSetupLink($member, $request->user());
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->validator->errors()->first('account')], 422);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Turns an approved member's login account into a staff or admin account (management accounts only).
     */
    public function upgrade(UpgradeMemberRequest $request, Member $member, AccountService $accounts): JsonResponse
    {
        try {
            $account = $accounts->upgradeMember($request->user(), $member, $request->validated());
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->validator->errors()->first('account'), 'errors' => $exception->errors()], 422);
        }

        return response()->json(['message' => __('app.members.upgrade.done'), 'data' => ['id' => $account->id, 'account_type' => $account->account_type->value]]);
    }

    /**
     * Sends the applicant a new e-mail verification link, for example after the first one could not be sent.
     */
    public function resendVerification(Request $request, Member $member, MemberEmailVerificationService $verification): JsonResponse
    {
        if ($member->email_verified_at !== null) {
            return response()->json(['message' => __('app.members.verification.already')], 422);
        }

        try {
            $verification->sendLink($member, $request->user());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => __('app.members.verification.sent')]);
    }

    /**
     * Update the member details.
     */
    public function update(UpdateMemberRequest $request, Member $member, MemberApplicationService $applications): JsonResource
    {
        $updated = $applications->updateDetails($member, $request->validated());

        return new MemberResource($updated->load('category'));
    }

    /**
     * Approve, reject or return an application to Pending.
     */
    public function review(
        ReviewMemberRequest $request,
        Member $member,
        MemberStatusService $statuses,
        HtmlSanitizer $sanitizer,
    ): JsonResource {
        $note = $request->filled('note') ? $sanitizer->sanitize($request->string('note')->value()) : null;

        $updated = $statuses->changeStatus(
            $member,
            MemberStatus::from($request->string('status')->value()),
            $request->user(),
            $note !== '' ? $note : null,
        );

        return new MemberResource($updated->load(['category', 'statusLogs.changedBy']));
    }
}
