<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class MemberAccountTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'kata-laluan-ahli-123';

    private function approve(Member $member, User $actor): Member
    {
        return app(MemberStatusService::class)->changeStatus($member, MemberStatus::Approved, $actor, null);
    }

    /**
     * Sets up the set-up link for a member and returns the token and address from it.
     *
     * @return array{token: string, email: string}
     */
    private function setupLinkFor(Member $member, User $admin): array
    {
        $url = $this->actingAs($admin)
            ->postJson(route('admin.api.members.setup-link', $member))
            ->assertOk()
            ->json('data.url');

        $parts = parse_url((string) $url);
        parse_str($parts['query'] ?? '', $query);
        $token = basename((string) parse_url($url, PHP_URL_PATH));

        return ['token' => $token, 'email' => $query['email']];
    }

    public function test_approving_a_member_creates_a_member_account_without_a_password(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create(['email' => 'ahli.baru@example.com']);

        $this->approve($member, $admin);

        $account = User::query()->where('member_id', $member->id)->firstOrFail();
        $this->assertTrue($account->isMember());
        $this->assertSame('ahli.baru@example.com', $account->email);
        $this->assertNull($account->password);
    }

    public function test_an_e_mail_already_in_use_does_not_block_approval(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create(['email' => 'sudah.guna@example.com']);
        User::factory()->viewer()->create(['email' => 'sudah.guna@example.com']);

        $approved = $this->approve($member, $admin);

        $this->assertSame(MemberStatus::Approved, $approved->status);
        $this->assertFalse(User::query()->where('member_id', $member->id)->exists());
    }

    public function test_only_approved_members_can_be_sent_a_set_up_link(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.members.setup-link', $member))
            ->assertUnprocessable();
    }

    public function test_member_sets_a_password_through_the_link_and_then_signs_in(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create(['email' => 'ahli@example.com']);
        $this->approve($member, $admin);

        ['token' => $token, 'email' => $email] = $this->setupLinkFor($member, $admin);

        // The member opens the link while signed out.
        Auth::logout();

        $this->get(route('password.setup', ['token' => $token, 'email' => $email]))->assertOk();

        $this->post(route('password.setup.store'), [
            'token' => $token,
            'email' => $email,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertRedirect(route('login'));

        // The link works once only.
        $this->post(route('password.setup.store'), [
            'token' => $token,
            'email' => $email,
            'password' => 'kata-laluan-lain-456',
            'password_confirmation' => 'kata-laluan-lain-456',
        ])->assertSessionHasErrors('token');

        $this->post(route('login'), ['email' => $email, 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs(User::query()->where('email', $email)->firstOrFail());
    }

    public function test_members_land_on_votes_and_cannot_open_committee_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create();
        $this->approve($member, $admin);
        $account = User::query()->where('member_id', $member->id)->firstOrFail();
        $account->forceFill(['password' => self::PASSWORD])->save();

        $this->actingAs($account)->get(route('admin.dashboard'))->assertRedirect(route('member.home'));
        $this->actingAs($account)->get(route('admin.positions.index'))->assertRedirect(route('member.home'));
        $this->actingAs($account)->get(route('admin.members.index'))->assertRedirect(route('member.home'));
        $this->actingAs($account)->getJson(route('admin.api.members.index'))->assertForbidden();
        $this->actingAs($account)->getJson(route('admin.api.dashboard.show'))->assertForbidden();
        $this->actingAs($account)->get(route('member.votes.index'))->assertOk();
        $this->actingAs($account)->get(route('admin.profile.index'))->assertRedirect(route('member.home'));
        $this->actingAs($account)->get(route('member.profile.index'))->assertOk();
        $this->actingAs($account)->getJson(route('admin.api.profile.show'))->assertForbidden();
        $this->actingAs($account)->getJson(route('member.api.profile.show'))->assertOk();
    }

    public function test_member_accounts_are_not_listed_among_system_accounts(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Pentadbir Utama']);
        $member = Member::factory()->create(['applicant_name' => 'Ahli Bernama']);
        $this->approve($member, $admin);

        $accounts = collect($this->actingAs($admin)->getJson(route('admin.api.accounts.index'))->json('data'));

        $this->assertFalse($accounts->contains('name', 'Ahli Bernama'));
        $this->assertTrue($accounts->contains('name', 'Pentadbir Utama'));
    }

    public function test_member_sidebar_and_pages_use_the_member_area_not_admin(): void
    {
        $member = User::factory()->create(['account_type' => 'member', 'role' => null, 'password' => self::PASSWORD]);

        $page = $this->actingAs($member)->get(route('member.votes.index'))->assertOk();

        $page->assertSee('href="'.route('member.votes.index').'"', false);
        $page->assertSee('href="'.route('member.profile.index').'"', false);
        $page->assertSee('href="'.route('member.activity.index').'"', false);
        $page->assertDontSee('href="'.route('admin.votes.index').'"', false);
        $page->assertDontSee('href="'.route('admin.profile.index').'"', false);
        $page->assertDontSee('href="'.route('admin.activity.index').'"', false);
    }
}
