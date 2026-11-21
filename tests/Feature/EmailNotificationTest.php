<?php

namespace Tests\Feature;

use App\Models\BusinessCategory;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\User;
use App\Services\Branding;
use App\Services\IntegrationSettings;
use App\Services\MemberApplicationService;
use App\Services\MemberEmailVerificationService;
use App\Services\ResendMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_is_sent_a_set_up_link_by_default_in_an_email_that_carries_the_logo(): void
    {
        Storage::fake(Branding::DISK);
        app(Branding::class)->storeLogo('logo-bytes', null);
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_setup'], 200)]);

        $this->actingAs(User::factory()->superadmin()->create())
            ->postJson(route('admin.api.accounts.store'), $this->accountPayload())
            ->assertCreated()
            ->assertJsonPath('setup.emailed', true)
            ->assertJsonPath('setup.url', null);

        $this->assertNull(User::query()->where('email', 'siti@example.com')->firstOrFail()->password);

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === ResendMailer::ENDPOINT
                && $request['to'] === ['siti@example.com']
                && str_contains((string) $request['html'], 'cid:email-logo')
                && $request['attachments'][0]['content_id'] === 'email-logo'
                && $request['attachments'][0]['content'] === base64_encode('logo-bytes')
                && str_contains((string) $request['html'], '/set-password/');
        });
    }

    public function test_without_e_mail_the_set_up_link_is_returned_once_for_the_administrator(): void
    {
        $response = $this->actingAs(User::factory()->superadmin()->create())
            ->postJson(route('admin.api.accounts.store'), $this->accountPayload())
            ->assertCreated()
            ->assertJsonPath('setup.emailed', false);

        $this->assertStringContainsString('/set-password/', (string) $response->json('setup.url'));
        Http::assertNothingSent();
    }

    public function test_the_password_is_required_when_the_set_up_link_is_switched_off(): void
    {
        $this->actingAs(User::factory()->superadmin()->create())
            ->postJson(route('admin.api.accounts.store'), $this->accountPayload(['send_setup_link' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_an_application_is_kept_when_the_verification_e_mail_cannot_be_sent(): void
    {
        $token = Invitation::factory()->create(['max_uses' => 50])->token;

        $this->post(route('registration.store', ['token' => $token]), $this->payload())
            ->assertRedirect(route('registration.success'))
            ->assertSessionHas('verification_sent', false);

        $this->assertTrue(Member::query()->where('email', 'ali@example.com')->whereNull('email_verified_at')->exists());
    }

    public function test_the_applicant_is_sent_a_verification_link_and_a_confirmation_once_the_link_is_opened(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_verify'], 200)]);
        $token = Invitation::factory()->create(['max_uses' => 50])->token;

        $this->post(route('registration.store', ['token' => $token]), $this->payload())
            ->assertRedirect(route('registration.success'))
            ->assertSessionHas('verification_sent', true);

        $member = Member::query()->where('email', 'ali@example.com')->firstOrFail();
        $this->assertNull($member->email_verified_at);

        $link = $this->verificationLinkSent();

        $this->get($link)->assertOk()->assertSee('E-mel disahkan');
        $this->assertNotNull($member->refresh()->email_verified_at);

        // The verification link and the confirmation e-mail.
        Http::assertSentCount(2);

        $this->get($link)->assertOk()->assertSee('Sudah disahkan');
        Http::assertSentCount(2);
    }

    public function test_a_verification_link_without_a_valid_signature_is_refused(): void
    {
        $member = Member::factory()->create(['email' => 'ali@example.com']);

        $this->get(route('registration.verify-email', ['member' => $member->id, 'email' => 'ali@example.com']))
            ->assertForbidden();

        $this->assertNull($member->refresh()->email_verified_at);
    }

    public function test_a_verification_link_stops_working_when_the_address_on_the_application_changes(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_old'], 200)]);
        $member = Member::factory()->create(['email' => 'ali@example.com']);

        app(MemberEmailVerificationService::class)->sendLink($member);
        $link = $this->verificationLinkSent();

        $member->forceFill(['email' => 'lain@example.com'])->save();

        $this->get($link)->assertOk()->assertSee('Pautan tidak sah');
        $this->assertNull($member->refresh()->email_verified_at);
    }

    public function test_changing_the_address_on_the_application_clears_its_verification(): void
    {
        $member = Member::factory()->create(['email' => 'ali@example.com', 'email_verified_at' => now()]);

        $this->updateAddress($member, 'ali@example.com');
        $this->assertNotNull($member->refresh()->email_verified_at);

        $this->updateAddress($member, 'baru@example.com');
        $this->assertNull($member->refresh()->email_verified_at);
    }

    public function test_management_can_send_the_verification_link_again_until_the_address_is_confirmed(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_again'], 200)]);
        $member = Member::factory()->create(['email' => 'ali@example.com']);

        $this->actingAs(User::factory()->viewer()->create())
            ->postJson(route('admin.api.members.verification.resend', $member))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.members.verification.resend', $member))
            ->assertOk();

        Http::assertSentCount(1);

        $member->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.members.verification.resend', $member))
            ->assertUnprocessable()
            ->assertJsonPath('message', __('app.members.verification.already'));

        Http::assertSentCount(1);
    }

    /**
     * Saves the application details with the given address through the same service the member page uses.
     */
    private function updateAddress(Member $member, string $email): void
    {
        app(MemberApplicationService::class)->updateDetails($member, [
            'applicant_name' => $member->applicant_name,
            'business_name' => $member->business_name,
            'business_category_id' => $member->business_category_id,
            'business_address' => $member->business_address,
            'phone' => $member->phone,
            'email' => $email,
            'ssm_no' => $member->ssm_no,
            'business_description' => '<p>Kedai runcit</p>',
            'business_needs' => '<p>Latihan</p>',
        ]);
    }

    /**
     * The verification link is the one in the e-mail that was sent, with its HTML entities decoded.
     */
    private function verificationLinkSent(): string
    {
        $html = '';

        Http::assertSent(function (HttpRequest $request) use (&$html): bool {
            if (str_contains((string) $request['html'], 'verify-email')) {
                $html = (string) $request['html'];

                return true;
            }

            return false;
        });

        preg_match('#https?://[^"\s<]*verify-email[^"\s<]*#', $html, $matches);

        return html_entity_decode($matches[0]);
    }

    private function connectResend(): void
    {
        $settings = app(IntegrationSettings::class);
        $settings->put('resend.api_key', 're_live_key', null);
        $settings->put('resend.from_email', 'info@example.com', null);
        $settings->markVerified('resend', null);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function accountPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Siti Staff',
            'email' => 'siti@example.com',
            'account_type' => 'staff',
            'role' => 'committee',
            'send_setup_link' => true,
        ], $overrides);
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
