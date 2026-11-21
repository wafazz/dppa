<?php

namespace Tests\Feature;

use App\Models\AccountPosition;
use App\Models\BusinessCategory;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'kata-laluan-selamat-123';

    public function test_superadmin_creates_admin_accounts_but_admin_cannot(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($superadmin)
            ->postJson(route('admin.api.accounts.store'), $this->accountPayload(['account_type' => 'admin']))
            ->assertCreated()
            ->assertJsonPath('data.account_type', 'admin');

        $this->actingAs($admin)
            ->postJson(route('admin.api.accounts.store'), $this->accountPayload(['account_type' => 'admin', 'email' => 'x@example.com']))
            ->assertForbidden();
    }

    public function test_admin_can_create_staff_only_with_a_position_below_its_own(): void
    {
        $admin = User::factory()->admin()->create(['account_position_id' => AccountPosition::factory()->create(['level' => 2])->id]);
        $lower = AccountPosition::factory()->create(['level' => 4]);
        $higher = AccountPosition::factory()->create(['level' => 1]);

        $this->actingAs($admin)
            ->postJson(route('admin.api.accounts.store'), $this->accountPayload(['account_position_id' => $lower->id]))
            ->assertCreated()
            ->assertJsonPath('data.account_position.id', $lower->id);

        $this->actingAs($admin)
            ->postJson(route('admin.api.accounts.store'), $this->accountPayload([
                'email' => 'atas@example.com',
                'account_position_id' => $higher->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account_position_id');
    }

    public function test_admin_without_a_position_cannot_give_any_position(): void
    {
        $admin = User::factory()->admin()->create();
        $position = AccountPosition::factory()->create(['level' => 5]);

        $this->actingAs($admin)
            ->postJson(route('admin.api.accounts.store'), $this->accountPayload(['account_position_id' => $position->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account_position_id');
    }

    public function test_admin_cannot_manage_other_admins_or_the_superadmin(): void
    {
        $admin = User::factory()->admin()->create(['account_position_id' => AccountPosition::factory()->create(['level' => 2])->id]);
        $otherAdmin = User::factory()->admin()->create();
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->putJson(route('admin.api.accounts.update', $otherAdmin), $this->updatePayload($otherAdmin))
            ->assertForbidden();

        $this->actingAs($admin)
            ->putJson(route('admin.api.accounts.update', $superadmin), $this->updatePayload($superadmin))
            ->assertForbidden();
    }

    public function test_admin_manages_only_staff_ranked_below_it(): void
    {
        $admin = User::factory()->admin()->create(['account_position_id' => AccountPosition::factory()->create(['level' => 2])->id]);
        $junior = User::factory()->create(['account_position_id' => AccountPosition::factory()->create(['level' => 5])->id]);
        $senior = User::factory()->create(['account_position_id' => AccountPosition::factory()->create(['level' => 1])->id]);

        $this->actingAs($admin)
            ->putJson(route('admin.api.accounts.update', $junior), $this->updatePayload($junior, ['can_invite' => true]))
            ->assertOk()
            ->assertJsonPath('data.can_invite', true);

        $this->actingAs($admin)
            ->putJson(route('admin.api.accounts.update', $senior), $this->updatePayload($senior))
            ->assertForbidden();
    }

    public function test_nobody_edits_their_own_account_through_the_accounts_api(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->putJson(route('admin.api.accounts.update', $superadmin), $this->updatePayload($superadmin))
            ->assertForbidden();
    }

    public function test_staff_cannot_open_account_management(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.accounts.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.api.accounts.index'))
            ->assertForbidden();
    }

    public function test_role_and_invitation_permission_apply_only_to_staff(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->postJson(route('admin.api.accounts.store'), $this->accountPayload([
                'account_type' => 'admin',
                'role' => 'viewer',
                'can_invite' => true,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.role', null)
            ->assertJsonPath('data.can_invite', false);
    }

    public function test_deactivated_account_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['email' => 'nonaktif@example.com']);

        $this->post(route('login'), ['email' => 'nonaktif@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivation_ends_an_existing_session_on_the_next_request(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $staff = User::factory()->create();

        $this->actingAs($staff)->get(route('admin.members.index'))->assertOk();

        $this->actingAs($superadmin)
            ->putJson(route('admin.api.accounts.update', $staff), $this->updatePayload($staff, ['is_active' => false]))
            ->assertOk();

        $staff->refresh();
        $this->assertFalse($staff->is_active);

        $this->actingAs($staff)->get(route('admin.members.index'))->assertRedirect(route('login'));
    }

    public function test_password_change_is_hashed_and_optional(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $staff = User::factory()->create();
        $before = $staff->password;

        $this->actingAs($superadmin)
            ->putJson(route('admin.api.accounts.update', $staff), $this->updatePayload($staff, ['password' => '']))
            ->assertOk();

        $this->assertSame($before, $staff->fresh()->password);

        $this->actingAs($superadmin)
            ->putJson(route('admin.api.accounts.update', $staff), $this->updatePayload($staff, ['password' => 'kata-laluan-baharu-456']))
            ->assertOk();

        $this->assertNotSame($before, $staff->fresh()->password);
    }

    public function test_superadmin_creates_account_positions_admin_cannot(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.account-positions.store'), [
                'name_bm' => 'Penolong Setiausaha',
                'level' => 6,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs(User::factory()->superadmin()->create())
            ->postJson(route('admin.api.account-positions.store'), [
                'name_bm' => 'Penolong Setiausaha',
                'level' => 6,
                'is_active' => true,
            ])
            ->assertCreated();
    }

    public function test_invitations_can_be_created_by_management_and_inviting_staff_only(): void
    {
        $inviter = User::factory()->canInvite()->create();
        $plainStaff = User::factory()->create();

        $this->actingAs($inviter)
            ->postJson(route('admin.api.invitations.store'), ['expires_in_days' => 7, 'max_uses' => 1, 'note' => 'Pengusaha kedai'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');

        $this->actingAs($plainStaff)
            ->postJson(route('admin.api.invitations.store'), ['expires_in_days' => 7, 'max_uses' => 1])
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.invitations.store'), ['expires_in_days' => 7, 'max_uses' => 1])
            ->assertCreated();
    }

    public function test_only_the_creator_or_management_can_revoke_an_invitation(): void
    {
        $inviter = User::factory()->canInvite()->create();
        $otherInviter = User::factory()->canInvite()->create();
        $invitation = Invitation::factory()->create(['created_by_user_id' => $inviter->id]);

        $this->actingAs($otherInviter)
            ->postJson(route('admin.api.invitations.revoke', $invitation))
            ->assertForbidden();

        $this->actingAs($inviter)
            ->postJson(route('admin.api.invitations.revoke', $invitation))
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');
    }

    public function test_registration_without_an_invitation_is_closed(): void
    {
        $this->get(route('registration.create'))
            ->assertOk()
            ->assertSee('Permohonan melalui jemputan sahaja')
            ->assertDontSee('Hantar permohonan');
    }

    public function test_valid_invitation_opens_the_form_and_registration_is_linked_to_it(): void
    {
        $invitation = Invitation::factory()->create(['max_uses' => 1]);

        $this->get(route('registration.invited', ['token' => $invitation->token]))
            ->assertOk()
            ->assertSee('Hantar permohonan');

        $this->post(route('registration.store', ['token' => $invitation->token]), $this->registrationPayload())
            ->assertRedirect(route('registration.success'));

        $this->assertSame(1, $invitation->fresh()->used_count);
        $this->assertSame($invitation->id, Member::query()->sole()->invitation_id);
    }

    public function test_invitation_cannot_be_used_more_than_its_limit(): void
    {
        $invitation = Invitation::factory()->create(['max_uses' => 1]);
        $url = route('registration.store', ['token' => $invitation->token]);

        $this->post($url, $this->registrationPayload())->assertRedirect(route('registration.success'));

        $this->post($url, $this->registrationPayload(['email' => 'kedua@example.com']))
            ->assertRedirect(route('registration.invited', ['token' => $invitation->token]));

        $this->assertDatabaseCount('members', 1);
        $this->assertSame(1, $invitation->fresh()->used_count);
    }

    public function test_expired_and_revoked_invitations_are_rejected(): void
    {
        $expired = Invitation::factory()->expired()->create();
        $revoked = Invitation::factory()->revoked()->create();

        $this->get(route('registration.invited', ['token' => $expired->token]))
            ->assertOk()
            ->assertSee('Pautan jemputan tidak sah');

        $this->get(route('registration.invited', ['token' => $revoked->token]))
            ->assertOk()
            ->assertSee('Pautan jemputan tidak sah');

        $this->post(route('registration.store', ['token' => $revoked->token]), $this->registrationPayload())
            ->assertRedirect(route('registration.invited', ['token' => $revoked->token]));

        $this->assertDatabaseCount('members', 0);
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->get(route('registration.invited', ['token' => str_repeat('a', 64)]))
            ->assertOk()
            ->assertSee('Pautan jemputan tidak sah');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function accountPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Akaun Uji',
            'email' => 'akaun.uji@example.com',
            'account_type' => 'staff',
            'password' => self::PASSWORD,
            'role' => 'committee',
            'can_invite' => false,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'role' => $user->role?->value ?? 'committee',
            'can_invite' => $user->can_invite,
            'account_position_id' => $user->account_position_id,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'applicant_name' => 'Ali bin Abu',
            'business_name' => 'Kedai Ali Enterprise',
            'business_category_id' => BusinessCategory::factory()->create()->id,
            'business_address' => 'No. 1, Jalan Puncak 1, Puncak Alam, Selangor',
            'phone' => '012-3456789',
            'email' => 'ali@example.com',
            'ssm_no' => null,
            'business_description' => '<p>Kedai runcit</p>',
            'business_needs' => '<p>Latihan pengurusan</p>',
            'consent' => '1',
        ], $overrides);
    }
}
