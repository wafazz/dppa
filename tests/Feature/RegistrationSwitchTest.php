<?php

namespace Tests\Feature;

use App\Models\BusinessCategory;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_open_by_default(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('admin.api.registration.show'))
            ->assertOk()
            ->assertJsonPath('data.open', true);
    }

    public function test_only_management_accounts_can_switch_registration(): void
    {
        $inviter = User::factory()->canInvite()->create();

        $this->actingAs($inviter)
            ->putJson(route('admin.api.registration.update'), ['open' => false])
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('admin.api.registration.update'), ['open' => false])
            ->assertOk()
            ->assertJsonPath('data.open', false);

        $this->actingAs(User::factory()->superadmin()->create())
            ->putJson(route('admin.api.registration.update'), ['open' => true])
            ->assertOk()
            ->assertJsonPath('data.open', true);
    }

    public function test_closing_registration_overrides_every_invitation_link(): void
    {
        $invitation = Invitation::factory()->create(['max_uses' => 5]);
        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('admin.api.registration.update'), ['open' => false]);

        $this->get(route('registration.invited', ['token' => $invitation->token]))
            ->assertOk()
            ->assertSee('Pendaftaran ditutup buat sementara')
            ->assertDontSee('Hantar permohonan');

        $this->post(route('registration.store', ['token' => $invitation->token]), $this->payload())
            ->assertRedirect(route('registration.invited', ['token' => $invitation->token]));

        $this->get(route('registration.create'))->assertOk()->assertSee('jemputan sahaja');

        $this->assertDatabaseCount('members', 0);
        $this->assertSame(0, $invitation->fresh()->used_count);
    }

    public function test_reopening_registration_restores_valid_links_only(): void
    {
        $valid = Invitation::factory()->create();
        $revoked = Invitation::factory()->revoked()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->putJson(route('admin.api.registration.update'), ['open' => false]);
        $this->actingAs($admin)->putJson(route('admin.api.registration.update'), ['open' => true]);

        $this->get(route('registration.invited', ['token' => $valid->token]))
            ->assertSee('Hantar permohonan');

        $this->post(route('registration.store', ['token' => $valid->token]), $this->payload())
            ->assertRedirect(route('registration.success'));

        $this->get(route('registration.invited', ['token' => $revoked->token]))
            ->assertSee('Pautan jemputan tidak sah');

        $this->assertDatabaseCount('members', 1);
    }

    public function test_the_switch_is_shown_as_closed_to_visitors_without_an_invitation(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('admin.api.registration.update'), ['open' => false]);

        $this->get(route('registration.create'))->assertOk()->assertSee('jemputan sahaja');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'applicant_name' => 'Ali bin Abu',
            'business_name' => 'Kedai Ali',
            'business_category_id' => BusinessCategory::factory()->create()->id,
            'business_address' => 'No. 1, Jalan Puncak 1, Puncak Alam',
            'phone' => '012-3456789',
            'email' => 'ali.switch@example.com',
            'ssm_no' => null,
            'business_description' => '<p>Kedai runcit</p>',
            'business_needs' => '<p>Latihan</p>',
            'consent' => '1',
        ];
    }
}
