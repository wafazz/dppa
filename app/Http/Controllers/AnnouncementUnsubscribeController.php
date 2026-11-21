<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\AnnouncementService;
use Illuminate\View\View;

class AnnouncementUnsubscribeController extends Controller
{
    /**
     * One-click opt-out from announcements. The link is signed, so it only works for the address it was sent to.
     */
    public function __invoke(Member $member, AnnouncementService $announcements): View
    {
        $announcements->unsubscribe($member);

        return view('public.unsubscribed');
    }
}
