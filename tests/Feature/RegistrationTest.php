<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\BusinessCategory;
use App\Models\Invitation;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_form_with_only_active_categories(): void
    {
        BusinessCategory::factory()->create(['name_bm' => 'Kategori Aktif Uji']);
        BusinessCategory::factory()->inactive()->create(['name_bm' => 'Kategori Tidak Aktif Uji']);

        $this->get(route('registration.invited', ['token' => $this->invitationToken()]))
            ->assertOk()
            ->assertSee('Kategori Aktif Uji')
            ->assertDontSee('Kategori Tidak Aktif Uji')
            ->assertSee('Hantar permohonan');
    }

    public function test_valid_application_is_stored_as_pending_with_consent_record(): void
    {
        $this->post(route('registration.store', ['token' => $this->invitationToken()]), $this->payload())
            ->assertRedirect(route('registration.success'));

        $member = Member::query()->sole();

        $this->assertSame(MemberStatus::Pending, $member->status);
        $this->assertNull($member->member_no);
        $this->assertNotNull($member->applied_at);
        $this->assertNotNull($member->consent_given_at);
        $this->assertSame(config('membership.consent_version'), $member->consent_version);
        $this->assertSame('ali@example.com', $member->email);
    }

    public function test_success_page_confirms_pending_status(): void
    {
        $this->get(route('registration.success'))
            ->assertOk()
            ->assertSee('Menunggu');
    }

    public function test_consent_is_required(): void
    {
        $this->post(route('registration.store', ['token' => $this->invitationToken()]), $this->payload(['consent' => null]))
            ->assertSessionHasErrors('consent');

        $this->assertDatabaseCount('members', 0);
    }

    public function test_honeypot_field_blocks_automated_submissions(): void
    {
        $this->post(route('registration.store', ['token' => $this->invitationToken()]), $this->payload(['website' => 'http://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertDatabaseCount('members', 0);
    }

    public function test_empty_rich_text_fields_are_rejected(): void
    {
        $this->post(route('registration.store', ['token' => $this->invitationToken()]), $this->payload([
            'business_description' => '<p><br></p>',
            'business_needs' => '   ',
        ]))->assertSessionHasErrors(['business_description', 'business_needs']);

        $this->assertDatabaseCount('members', 0);
    }

    public function test_invalid_phone_and_email_are_rejected(): void
    {
        $this->post(route('registration.store', ['token' => $this->invitationToken()]), $this->payload([
            'phone' => 'abc',
            'email' => 'bukan-emel',
        ]))->assertSessionHasErrors(['phone', 'email']);
    }

    public function test_inactive_category_cannot_be_selected(): void
    {
        $inactive = BusinessCategory::factory()->inactive()->create();

        $this->post(route('registration.store', ['token' => $this->invitationToken()]), $this->payload(['business_category_id' => $inactive->id]))
            ->assertSessionHasErrors('business_category_id');
    }

    public function test_active_duplicate_ssm_number_is_rejected(): void
    {
        Member::factory()->create(['ssm_no' => '202601012345-X']);

        $this->post(route('registration.store', ['token' => $this->invitationToken()]), $this->payload(['ssm_no' => '202601012345-X']))
            ->assertSessionHasErrors('ssm_no');

        $this->assertDatabaseCount('members', 1);
    }

    public function test_ssm_number_of_a_rejected_application_can_be_reused(): void
    {
        Member::factory()->rejected()->create(['ssm_no' => '202601012345-X']);

        $this->post(route('registration.store', ['token' => $this->invitationToken()]), $this->payload(['ssm_no' => '202601012345-X']))
            ->assertRedirect(route('registration.success'));

        $this->assertDatabaseCount('members', 2);
    }

    public function test_script_and_event_handlers_are_removed_before_storage(): void
    {
        $this->post(route('registration.store', ['token' => $this->invitationToken()]), $this->payload([
            'business_description' => '<p onclick="steal()"><strong>Kedai</strong> <script>alert(1)</script>runcit</p>',
        ]))->assertRedirect();

        $description = Member::query()->sole()->business_description;

        $this->assertStringNotContainsString('<script', $description);
        $this->assertStringNotContainsString('onclick', $description);
        $this->assertStringContainsString('<strong>Kedai</strong>', $description);
    }

    public function test_public_submissions_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('registration.store', ['token' => $this->invitationToken()]), ['website' => 'x']);
        }

        $this->post(route('registration.store', ['token' => $this->invitationToken()]), ['website' => 'x'])->assertStatus(429);
    }

    private ?string $token = null;

    /**
     * A usable invitation shared by the tests in one case.
     */
    private function invitationToken(): string
    {
        return $this->token ??= Invitation::factory()->create(['max_uses' => 50])->token;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'applicant_name' => 'Ali bin Abu',
            'business_name' => 'Kedai Ali Enterprise',
            'business_category_id' => BusinessCategory::factory()->create()->id,
            'business_address' => 'No. 1, Jalan Puncak 1, Puncak Alam, Selangor',
            'phone' => '012-3456789',
            'email' => 'ali@example.com',
            'ssm_no' => '202601012345-X',
            'business_description' => '<p>Kedai runcit dan bekalan dapur</p>',
            'business_needs' => '<p>Latihan pengurusan kewangan</p>',
            'consent' => '1',
        ], $overrides);
    }
}
