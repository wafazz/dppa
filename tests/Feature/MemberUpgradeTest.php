<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'kata-laluan-ahli-123';

    /**
     * An approved member whose login account has chosen a password.
     */
    private function memberWithPassword(): Member
    {
        $member = Member::factory()->create(['email' => 'ahli@example.com']);
        app(MemberStatusService::class)->changeStatus($member, MemberStatus::Approved, User::factory()->superadmin()->create(), null);

        $account = User::query()->where('member_id', $member->id)->firstOrFail();
        $account->forceFill(['password' => self::PASSWORD])->save();

        return $member->refresh();
    }

    private function upgradeUrl(Member $member): string
    {
        return route('admin.api.members.upgrade', $member);
    }

    public function test_superadmin_can_upgrade_a_member_to_an_admin_account_listed_under_system_accounts(): void
    {
        $member = $this->memberWithPassword();
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->postJson($this->upgradeUrl($member), ['account_type' => 'admin'])
            ->assertOk();

        $account = User::query()->where('member_id', $member->id)->firstOrFail();
        $this->assertSame('admin', $account->account_type->value);
        $this->assertNotNull($account->password, 'The member keeps the password they chose.');
        $this->assertFalse($account->isMember());

        $this->actingAs($superadmin)
            ->getJson(route('admin.api.accounts.index'))
            ->assertOk()
            ->assertJsonFragment(['email' => 'ahli@example.com']);
    }

    public function test_admin_can_upgrade_to_staff_but_not_to_admin(): void
    {
        $member = $this->memberWithPassword();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson($this->upgradeUrl($member), ['account_type' => 'admin'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson($this->upgradeUrl($member), ['account_type' => 'staff', 'role' => 'committee', 'can_invite' => true])
            ->assertOk();

        $account = User::query()->where('member_id', $member->id)->firstOrFail();
        $this->assertSame('staff', $account->account_type->value);
        $this->assertTrue((bool) $account->can_invite);
    }

    public function test_a_staff_role_is_required_when_upgrading_to_staff(): void
    {
        $member = $this->memberWithPassword();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->upgradeUrl($member), ['account_type' => 'staff'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_committee_viewers_and_staff_cannot_upgrade_members(): void
    {
        $member = $this->memberWithPassword();

        $this->actingAs(User::factory()->viewer()->create())
            ->postJson($this->upgradeUrl($member), ['account_type' => 'staff', 'role' => 'committee'])
            ->assertForbidden();

        $this->assertSame('member', User::query()->where('member_id', $member->id)->firstOrFail()->account_type->value);
    }

    public function test_a_member_without_a_password_cannot_be_upgraded_yet(): void
    {
        $member = Member::factory()->create(['email' => 'belum@example.com']);
        app(MemberStatusService::class)->changeStatus($member, MemberStatus::Approved, User::factory()->superadmin()->create(), null);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->upgradeUrl($member), ['account_type' => 'staff', 'role' => 'committee'])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('app.members.upgrade.password_first'));
    }

    public function test_a_pending_member_without_an_account_cannot_be_upgraded(): void
    {
        $member = Member::factory()->create(['email' => 'pending@example.com']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson($this->upgradeUrl($member), ['account_type' => 'staff', 'role' => 'committee'])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('app.members.upgrade.no_account'));
    }

    public function test_the_members_list_says_which_members_can_be_upgraded(): void
    {
        $ready = $this->memberWithPassword();
        $notReady = Member::factory()->create(['email' => 'pending@example.com']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('admin.api.members.index'))
            ->assertOk();

        $rows = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($rows[$ready->id]['can_upgrade']);
        $this->assertFalse($rows[$notReady->id]['can_upgrade']);

    }

    public function test_the_upgrade_is_written_to_the_activity_log(): void
    {
        $member = $this->memberWithPassword();

        $this->actingAs(User::factory()->superadmin()->create())
            ->postJson($this->upgradeUrl($member), ['account_type' => 'admin'])
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', ['event' => 'account.upgraded']);
    }
}
