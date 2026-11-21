<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\MemberEmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationVerificationController extends Controller
{
    /**
     * Confirms the applicant's e-mail. The route is signed; the address in the link must still be the one on the application.
     */
    public function verify(Request $request, Member $member, MemberEmailVerificationService $verification): View
    {
        if ($request->query('email') !== $member->email) {
            return view('public.email_verification', ['status' => 'invalid']);
        }

        $confirmedNow = $verification->verify($member);

        return view('public.email_verification', ['status' => $confirmedNow ? 'verified' : 'already']);
    }
}
