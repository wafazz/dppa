<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberApplicationRequest;
use App\Models\BusinessCategory;
use App\Models\Invitation;
use App\Models\Member;
use App\Services\InvitationService;
use App\Services\MemberApplicationService;
use App\Services\MemberEmailVerificationService;
use App\Services\RegistrationSwitch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class RegistrationController extends Controller
{
    /**
     * Registration without an invitation link is closed.
     */
    public function closed(): View
    {
        return view('public.invite_required');
    }

    /**
     * Show the application form when the invitation link is usable.
     */
    public function create(string $token, RegistrationSwitch $switch): View
    {
        // Switching registration off overrides every invitation link.
        if (! $switch->isOpen()) {
            return view('public.registration_closed');
        }

        $invitation = Invitation::query()->where('token', $token)->first();

        if ($invitation === null || ! $invitation->isUsable()) {
            return view('public.invalid_invite');
        }

        return view('public.register', [
            'token' => $token,
            'categories' => BusinessCategory::query()->active()->get(['id', 'name_bm', 'name_en']),
        ]);
    }

    /**
     * Store a new application with status Pending and count one use of the invitation.
     */
    public function store(
        string $token,
        StoreMemberApplicationRequest $request,
        InvitationService $invitations,
        MemberApplicationService $applications,
        MemberEmailVerificationService $verification,
        RegistrationSwitch $switch,
    ): RedirectResponse {
        if (! $switch->isOpen()) {
            return redirect()->route('registration.invited', ['token' => $token]);
        }

        try {
            $member = $invitations->redeem(
                $token,
                fn (Invitation $invitation) => $applications->submit($request->validated(), $invitation),
            );
        } catch (ValidationException) {
            return redirect()->route('registration.invited', ['token' => $token]);
        }

        return redirect()
            ->route('registration.success')
            ->with('verification_sent', $this->sendVerificationLink($verification, $member));
    }

    /**
     * Confirm that the application was received.
     */
    public function success(): View
    {
        return view('public.success');
    }

    /**
     * The application is kept even when the e-mail cannot be sent; the committee can resend it from the member record.
     */
    private function sendVerificationLink(MemberEmailVerificationService $verification, Member $member): bool
    {
        try {
            $verification->sendLink($member);
        } catch (RuntimeException $exception) {
            Log::warning('Membership verification e-mail was not sent.', ['member_id' => $member->id, 'reason' => $exception->getMessage()]);

            return false;
        }

        return true;
    }
}
