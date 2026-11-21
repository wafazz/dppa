<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\AccountPosition;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Builds the sidebar: which items a user may see, their submenus, and the counter badges.
 * Badges are computed here so the same rules that decide visibility also decide the numbers.
 */
class SidebarMenu
{
    /**
     * Member totals per status for the Members submenu, plus the all-members total under 'all'.
     *
     * @return array<string, int>
     */
    private function memberCounts(User $user): array
    {
        $counts = [
            'all' => 0,
            MemberStatus::Pending->value => 0,
            MemberStatus::Approved->value => 0,
            MemberStatus::Rejected->value => 0,
        ];

        if (! Gate::forUser($user)->allows('view-members')) {
            return $counts;
        }

        $byStatus = Member::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        foreach ($byStatus as $status => $total) {
            $counts[$status] = (int) $total;
            $counts['all'] += (int) $total;
        }

        return $counts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(User $user): array
    {
        $memberCounts = $this->memberCounts($user);
        $pendingCount = $memberCounts[MemberStatus::Pending->value];

        $invitationQuery = Invitation::query()->usable();

        if (! $user->isManagement()) {
            $invitationQuery->where('created_by_user_id', $user->id);
        }

        $committee = ! $user->isMember();

        // Members see their own pages under /member; everyone else uses /admin.
        $area = $user->isMember() ? 'member' : 'admin';

        $items = [
            $this->item(__('app.nav.dashboard'), 'dashboard', route('admin.dashboard'), request()->routeIs('admin.dashboard'), visible: $committee),
            $this->item(
                __('app.nav.profile'),
                'profile',
                route($area.'.profile.index'),
                request()->routeIs($area.'.profile.*'),
                // Shows 1 until the account has a two-factor sign-in method switched on.
                badge: app(TwoFactorService::class)->enabledMethods($user) === [] ? 1 : 0,
                badgeClass: 'text-bg-warning',
            ),
            $this->item(
                __('app.nav.members'),
                'members',
                route('admin.members.index'),
                request()->routeIs('admin.members.*'),
                visible: Gate::forUser($user)->allows('view-members'),
                badge: $pendingCount,
                badgeClass: 'text-bg-warning',
                children: [
                    $this->child(__('app.nav.members_all'), route('admin.members.index'), $this->inSection('admin.members.*', 'status', null), badge: $memberCounts['all']),
                    $this->child(__('app.nav.members_pending'), route('admin.members.index', ['status' => 'pending']), $this->inSection('admin.members.*', 'status', 'pending'), badge: $memberCounts[MemberStatus::Pending->value]),
                    $this->child(__('app.nav.members_approved'), route('admin.members.index', ['status' => 'approved']), $this->inSection('admin.members.*', 'status', 'approved'), badge: $memberCounts[MemberStatus::Approved->value]),
                    $this->child(__('app.nav.members_rejected'), route('admin.members.index', ['status' => 'rejected']), $this->inSection('admin.members.*', 'status', 'rejected'), badge: $memberCounts[MemberStatus::Rejected->value]),
                ],
            ),
            $this->item(
                __('app.nav.positions'),
                'positions',
                route('admin.positions.index'),
                request()->routeIs('admin.positions.*'),
                visible: $committee,
                children: [
                    $this->child(__('app.nav.positions_list'), route('admin.positions.index'), $this->inSection('admin.positions.*', 'tab', null)),
                    $this->child(__('app.nav.positions_current'), route('admin.positions.index', ['tab' => 'current']), $this->inSection('admin.positions.*', 'tab', 'current')),
                    $this->child(__('app.nav.positions_history'), route('admin.positions.index', ['tab' => 'history']), $this->inSection('admin.positions.*', 'tab', 'history')),
                ],
            ),
            $this->item(__('app.nav.categories'), 'categories', route('admin.categories.index'), request()->routeIs('admin.categories.*'), visible: $committee),
            $this->item(
                __('app.nav.accounts'),
                'accounts',
                route('admin.accounts.index'),
                request()->routeIs('admin.accounts.*'),
                visible: Gate::forUser($user)->allows('manage-accounts'),
                children: [
                    $this->child(
                        __('app.nav.accounts_list'),
                        route('admin.accounts.index'),
                        $this->inSection('admin.accounts.*', 'tab', null),
                        badge: User::query()->where('is_active', true)->count(),
                    ),
                    $this->child(
                        __('app.nav.accounts_positions'),
                        route('admin.accounts.index', ['tab' => 'positions']),
                        $this->inSection('admin.accounts.*', 'tab', 'positions'),
                        badge: AccountPosition::query()->where('is_active', true)->count(),
                    ),
                ],
            ),
            $this->item(
                __('app.nav.invitations'),
                'invitations',
                route('admin.invitations.index'),
                request()->routeIs('admin.invitations.*'),
                visible: Gate::forUser($user)->allows('manage-invitations'),
                badge: $invitationQuery->count(),
                badgeClass: 'text-bg-success',
            ),
            $this->item(
                __('app.nav.votes'),
                'votes',
                route($area.'.votes.index'),
                request()->routeIs($area.'.votes.*'),
                // Votes this account can take part in and has not yet voted in.
                badge: $this->votesWaiting($user),
                badgeClass: 'text-bg-warning',
                children: [
                    $this->child(__('app.nav.votes_all'), route($area.'.votes.index'), $this->inSection($area.'.votes.*', 'status', null)),
                    $this->child(__('app.nav.votes_open'), route($area.'.votes.index', ['status' => 'open']), $this->inSection($area.'.votes.*', 'status', 'open')),
                    $this->child(__('app.nav.votes_closed'), route($area.'.votes.index', ['status' => 'closed']), $this->inSection($area.'.votes.*', 'status', 'closed')),
                ],
            ),
            $this->item(
                __('app.nav.activity'),
                'activity',
                route($area.'.activity.index'),
                request()->routeIs($area.'.activity.*'),
            ),
            $this->item(
                __('app.nav.settings'),
                'settings',
                route('admin.settings.index'),
                request()->routeIs('admin.settings.*', 'admin.email-templates.*', 'admin.announcements.*'),
                visible: Gate::forUser($user)->allows('manage-settings'),
                children: [
                    $this->child(__('app.nav.settings'), route('admin.settings.index'), request()->routeIs('admin.settings.*')),
                    $this->child(__('app.nav.email_templates'), route('admin.email-templates.index'), request()->routeIs('admin.email-templates.*')),
                    $this->child(__('app.nav.announcements'), route('admin.announcements.index'), request()->routeIs('admin.announcements.*')),
                ],
            ),
        ];

        return array_values(array_filter($items, fn (array $item): bool => $item['visible']));
    }

    /**
     * Open votes the account is eligible for and has not voted in yet.
     */
    private function votesWaiting(User $user): int
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
                ->where('vote_receipts.user_id', $user->id))
            ->count();
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function item(
        string $label,
        string $icon,
        string $url,
        bool $active,
        bool $visible = true,
        int $badge = 0,
        string $badgeClass = 'text-bg-secondary',
        array $children = [],
    ): array {
        $childActive = collect($children)->contains(fn (array $child): bool => $child['active']);

        return [
            'label' => $label,
            'icon' => $icon,
            'url' => $url,
            'active' => $active || $childActive,
            'visible' => $visible,
            'badge' => $badge > 0 ? $badge : null,
            'badge_class' => $badgeClass,
            'children' => $children,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function child(string $label, string $url, bool $active, int $badge = 0): array
    {
        return [
            'label' => $label,
            'url' => $url,
            'active' => $active,
            'badge' => $badge > 0 ? $badge : null,
            'badge_class' => 'text-bg-secondary',
        ];
    }

    /**
     * A submenu item is active only on its own section's pages and with its query value.
     */
    private function inSection(string $routePattern, string $name, ?string $value): bool
    {
        return request()->routeIs($routePattern) && $this->queryIs($name, $value);
    }

    /**
     * True when the current request has the given query value (or none, when $value is null).
     */
    private function queryIs(string $name, ?string $value): bool
    {
        $current = request()->query($name);

        return $value === null ? ($current === null || $current === '') : $current === $value;
    }
}
