<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Models\User;
use App\Services\EmailNotices;
use App\Services\InvitationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationController extends Controller
{
    /**
     * Management accounts see every invitation; other inviters see only their own.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $invitations = Invitation::query()
            ->with('createdBy')
            ->when(! $user->isManagement(), fn ($query) => $query->where('created_by_user_id', $user->id))
            ->latest('id')
            ->paginate(20);

        return InvitationResource::collection($invitations);
    }

    public function store(StoreInvitationRequest $request, InvitationService $invitations): JsonResponse|JsonResource
    {
        $invitation = $invitations->create($request->user(), $request->validated());

        // Sent straight to the applicant when an address was given; the link is still returned for copying.
        $emailed = $invitation->recipient_email !== null && app(EmailNotices::class)->deliver(
            'invitation',
            $invitation->recipient_email,
            ['date' => $invitation->expires_at->translatedFormat('j M Y, H:i')],
            route('registration.invited', ['token' => $invitation->token]),
        );

        return (new InvitationResource($invitation->load('createdBy')))
            ->additional(['emailed' => $emailed])
            ->response()
            ->setStatusCode(201);
    }

    public function revoke(Request $request, Invitation $invitation, InvitationService $invitations): JsonResource
    {
        $this->ensureCanRevoke($request->user(), $invitation);

        return new InvitationResource($invitations->revoke($invitation)->load('createdBy'));
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureCanRevoke(User $user, Invitation $invitation): void
    {
        if (! $user->isManagement() && $invitation->created_by_user_id !== $user->id) {
            throw new AuthorizationException(__('app.validation.revoke_own_only'));
        }
    }
}
