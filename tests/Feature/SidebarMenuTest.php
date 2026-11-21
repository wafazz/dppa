<?php

namespace Tests\Feature;

use App\Models\AccountPosition;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\User;
use App\Services\SidebarMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_applications_and_active_invitations_are_shown_as_badges(): void
    {
        Member::factory()->count(2)->create();
        Member::factory()->approved()->create();
        Invitation::factory()->count(3)->create();
        Invitation::factory()->revoked()->create();

        $items = collect(app(SidebarMenu::class)->items(User::factory()->admin()->create()))->keyBy('icon');

        $this->assertSame(2, $items['members']['badge']);
        $this->assertSame(3, $items['invitations']['badge']);
    }

    public function test_menus_follow_the_account_permissions(): void
    {
        $viewer = collect(app(SidebarMenu::class)->items(User::factory()->viewer()->create()))->pluck('icon')->all();
        $this->assertNotContains('accounts', $viewer);
        $this->assertNotContains('invitations', $viewer);
        $this->assertNotContains('settings', $viewer);
        $this->assertNotContains('members', $viewer);

        $admin = collect(app(SidebarMenu::class)->items(User::factory()->admin()->create()))->pluck('icon')->all();
        $this->assertContains('accounts', $admin);
        $this->assertContains('settings', $admin);
    }

    public function test_members_submenu_links_to_each_status(): void
    {
        $members = collect(app(SidebarMenu::class)->items(User::factory()->create()))->firstWhere('icon', 'members');

        $urls = collect($members['children'])->pluck('url')->all();

        $this->assertContains(route('admin.members.index', ['status' => 'pending']), $urls);
        $this->assertContains(route('admin.members.index', ['status' => 'approved']), $urls);
        $this->assertContains(route('admin.members.index', ['status' => 'rejected']), $urls);
    }

    public function test_accounts_submenu_shows_counts_of_active_accounts_and_positions(): void
    {
        User::factory()->admin()->create();
        User::factory()->inactive()->create();
        AccountPosition::factory()->count(2)->create(['is_active' => true]);
        AccountPosition::factory()->create(['is_active' => false]);

        $accounts = collect(app(SidebarMenu::class)->items(User::factory()->superadmin()->create()))
            ->firstWhere('icon', 'accounts');
        $badges = collect($accounts['children'])->pluck('badge', 'label')->all();

        $this->assertSame(2, $badges[__('app.nav.accounts_list')]);
        $this->assertSame(2, $badges[__('app.nav.accounts_positions')]);
    }

    public function test_members_submenu_shows_counts_for_each_status(): void
    {
        Member::factory()->count(2)->create();
        Member::factory()->approved()->create();
        Member::factory()->rejected()->create();

        $members = collect(app(SidebarMenu::class)->items(User::factory()->admin()->create()))
            ->firstWhere('icon', 'members');
        $badges = collect($members['children'])->pluck('badge', 'label')->all();

        $this->assertSame(4, $badges[__('app.nav.members_all')]);
        $this->assertSame(2, $badges[__('app.nav.members_pending')]);
        $this->assertSame(1, $badges[__('app.nav.members_approved')]);
        $this->assertSame(1, $badges[__('app.nav.members_rejected')]);
    }

    public function test_every_account_has_a_profile_item_that_warns_until_two_factor_is_on(): void
    {
        $viewer = User::factory()->viewer()->create();

        $profile = collect(app(SidebarMenu::class)->items($viewer))->firstWhere('icon', 'profile');
        $this->assertSame(1, $profile['badge']);

        $viewer->forceFill(['two_factor_email_enabled' => true])->save();
        $profile = collect(app(SidebarMenu::class)->items($viewer->fresh()))->firstWhere('icon', 'profile');
        $this->assertNull($profile['badge']);
    }
}
