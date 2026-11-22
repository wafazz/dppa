<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberDashboard;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * Dashboard: the first page after login.
     */
    public function dashboard(): View
    {
        return $this->page('admin.dashboard.index');
    }

    /**
     * Member database list page.
     */
    public function members(): View
    {
        return $this->page('admin.members.index');
    }

    /**
     * Membership fee types (administrators only).
     */
    public function fees(): View
    {
        return $this->page('admin.fees.index');
    }

    /**
     * Invoices, payments and the billing summary.
     */
    public function invoices(): View
    {
        return $this->page('admin.invoices.index');
    }

    /**
     * Chamber bank details and billing rules (administrators only).
     */
    public function paymentSettings(): View
    {
        return $this->page('admin.payment-settings.index');
    }

    /**
     * The member's own dashboard: membership, votes waiting for them and their recent activity.
     */
    public function memberDashboard(MemberDashboard $dashboard): View
    {
        return view('member.dashboard', ['dashboard' => $dashboard->build(request()->user())]);
    }

    /**
     * Member detail page.
     */
    public function memberShow(Member $member): View
    {
        return $this->page('admin.members.show', ['memberId' => $member->id]);
    }

    /**
     * Chamber positions and appointment history page.
     */
    public function positions(): View
    {
        return $this->page('admin.positions.index');
    }

    /**
     * Business category management page.
     */
    public function categories(): View
    {
        return $this->page('admin.categories.index');
    }

    /**
     * Integration settings (email and WhatsApp API keys) for management accounts.
     */
    public function settings(): View
    {
        return $this->page('admin.settings.index');
    }

    /**
     * Wording of the automatic e-mails (administrators only).
     */
    public function emailTemplates(): View
    {
        return $this->page('admin.email-templates.index');
    }

    /**
     * Bulk announcements to members who agreed to receive them (administrators only).
     */
    public function announcements(): View
    {
        return $this->page('admin.announcements.index');
    }

    /**
     * Account management page (management accounts only).
     */
    public function accounts(): View
    {
        return $this->page('admin.accounts.index');
    }

    /**
     * Invitation management page.
     */
    public function invitations(): View
    {
        return $this->page('admin.invitations.index');
    }

    /**
     * Votes: every account sees the votes it may take part in. Management sees all of them.
     */
    public function votes(): View
    {
        $user = request()->user();

        return $this->page('admin.votes.index', [
            'canCreateVotes' => $user->canCreateVotes(),
            'isManagement' => $user->isManagement(),
        ]);
    }

    /**
     * Profile page: every signed-in account can update its name, password and display picture.
     */
    public function profile(): View
    {
        return $this->page('admin.profile.index');
    }

    /**
     * Render an admin page with the permissions the Vue components need to show or hide actions.
     *
     * @param  array<string, mixed>  $props
     */
    private function page(string $view, array $props = []): View
    {
        $user = request()->user();

        return view($view, [
            'props' => $props + [
                'currentUser' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'isSuperadmin' => $user->isSuperadmin(),
                    'level' => $user->managementLevel(),
                ],
                'can' => [
                    'viewMembers' => Gate::allows('view-members'),
                    'manageMembers' => Gate::allows('manage-members'),
                    'exportMembers' => Gate::allows('export-members'),
                    'manageAppointments' => Gate::allows('manage-appointments'),
                    'managePositions' => Gate::allows('manage-positions'),
                    'manageCategories' => Gate::allows('manage-categories'),
                    'manageAccounts' => Gate::allows('manage-accounts'),
                    'manageAccountPositions' => Gate::allows('manage-account-positions'),
                    'manageInvitations' => Gate::allows('manage-invitations'),
                    'manageSettings' => Gate::allows('manage-settings'),
                    'manageRegistration' => Gate::allows('manage-registration'),
                    'manageBranding' => Gate::allows('manage-branding'),
                ],
            ],
        ]);
    }
}
