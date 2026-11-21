<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\ChamberPosition;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\PositionAppointment;
use App\Models\User;
use App\Models\Vote;
use App\Services\AccountService;
use App\Services\DeviceAlerts;
use App\Services\IntegrationSettings;
use App\Services\MemberStatusService;
use App\Services\ResendMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailTemplatesAndNoticesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_open_the_email_template_editor(): void
    {
        $this->actingAs(User::factory()->viewer()->create())
            ->getJson(route('admin.api.email-templates.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('admin.api.email-templates.index'))
            ->assertOk()
            ->assertJsonFragment(['key' => 'account_setup']);
    }

    public function test_a_saved_wording_is_used_for_the_matching_e_mail_and_can_be_reset(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_custom'])]);
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->putJson(route('admin.api.email-templates.update', ['type' => 'account_setup', 'locale' => 'en']), [
                'subject' => 'Welcome aboard',
                'body' => "Dear {name},\n\nYour chamber account is ready.",
                'button' => 'Set my password',
            ])
            ->assertOk()
            ->assertJsonPath('data.customized', true);

        $this->actingAs(User::factory()->superadmin()->create())
            ->postJson(route('admin.api.accounts.store'), [
                'name' => 'Siti Staff',
                'email' => 'siti@example.com',
                'account_type' => 'staff',
                'role' => 'committee',
                'send_setup_link' => true,
            ])
            ->assertCreated();

        Http::assertSent(function (HttpRequest $request): bool {
            return $request['subject'] === 'Welcome aboard'
                && str_contains((string) $request['html'], 'Dear Siti Staff')
                && str_contains((string) $request['html'], 'Set my password');
        });

        $this->actingAs($admin)
            ->deleteJson(route('admin.api.email-templates.destroy', ['type' => 'account_setup', 'locale' => 'en']))
            ->assertOk()
            ->assertJsonPath('data.customized', false)
            ->assertJsonPath('data.subject', 'Set up your login account');
    }

    public function test_wording_typed_into_the_editor_is_shown_as_text_and_not_as_markup(): void
    {
        $admin = User::factory()->admin()->create();

        $html = (string) $this->actingAs($admin)
            ->postJson(route('admin.api.email-templates.preview', ['type' => 'member_verified', 'locale' => 'en']), [
                'subject' => 'Hello',
                'body' => '<script>alert(1)</script> {name}',
            ])
            ->assertOk()
            ->json('data.html');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_the_editor_rejects_unknown_types_and_languages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/admin/api/email-templates/not_a_type/en')->assertNotFound();
        $this->actingAs($admin)->getJson('/admin/api/email-templates/account_setup/fr')->assertNotFound();
    }

    public function test_an_approval_and_a_rejection_are_e_mailed_with_the_member_number(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_decision'])]);
        $actor = User::factory()->admin()->create();

        $approved = Member::factory()->create(['email' => 'ali@example.com', 'business_name' => 'Kedai Ali']);
        app(MemberStatusService::class)->changeStatus($approved, MemberStatus::Approved, $actor, null);

        Http::assertSent(fn (HttpRequest $request): bool => $request['to'] === ['ali@example.com']
            && str_contains((string) $request['html'], 'Kedai Ali')
            && str_contains((string) $request['html'], $approved->refresh()->member_no));

        $rejected = Member::factory()->create(['email' => 'lain@example.com']);
        app(MemberStatusService::class)->changeStatus($rejected, MemberStatus::Rejected, $actor, 'Dokumen tidak lengkap');

        Http::assertSent(fn (HttpRequest $request): bool => $request['to'] === ['lain@example.com']);
        Http::assertSentCount(2);
    }

    public function test_a_password_reset_link_is_e_mailed_only_to_an_active_account_and_the_answer_is_the_same(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_reset'])]);
        $user = User::factory()->admin()->create(['email' => 'admin@example.com']);
        $inactive = User::factory()->admin()->create(['email' => 'nonaktif@example.com', 'is_active' => false]);

        $this->post(route('password.email'), ['email' => 'admin@example.com'])
            ->assertSessionHas('status', __('app.auth.reset_sent'));

        $this->post(route('password.email'), ['email' => 'nonaktif@example.com'])
            ->assertSessionHas('status', __('app.auth.reset_sent'));

        $this->post(route('password.email'), ['email' => 'tiada@example.com'])
            ->assertSessionHas('status', __('app.auth.reset_sent'));

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => $request['to'] === [$user->email]
            && str_contains((string) $request['html'], '/set-password/'));
        $this->assertNotNull($inactive);
    }

    public function test_a_sign_in_from_a_new_browser_is_reported_but_the_first_browser_is_not(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_device'])]);
        $user = User::factory()->admin()->create(['email' => 'device@example.com']);
        $alerts = app(DeviceAlerts::class);

        $alerts->check($user, $this->request('Browser A', '203.0.113.9'));
        Http::assertNothingSent();

        $alerts->check($user, $this->request('Browser A', '203.0.113.9'));
        Http::assertNothingSent();

        $alerts->check($user, $this->request('Browser B', '198.51.100.7'));
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains((string) $request['html'], '198.51.100.7'));
    }

    public function test_verification_reminders_go_out_once_a_day_after_applying(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_reminder'])]);
        $due = Member::factory()->create(['email' => 'due@example.com', 'applied_at' => now()->subDays(2), 'verification_reminded_at' => null]);
        Member::factory()->create(['email' => 'baru@example.com', 'applied_at' => now(), 'verification_reminded_at' => null]);

        $this->artisan('pa:send-email-reminders')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertNotNull($due->refresh()->verification_reminded_at);

        $this->artisan('pa:send-email-reminders')->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_a_term_ending_within_thirty_days_is_reminded_once(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_term'])]);
        $member = Member::factory()->approved()->create(['email' => 'pengerusi@example.com']);
        $appointment = PositionAppointment::factory()->current()->create([
            'member_id' => $member->id,
            'chamber_position_id' => ChamberPosition::factory()->create()->id,
            'term_end' => now()->addDays(10)->toDateString(),
        ]);

        $this->artisan('pa:send-email-reminders')->assertSuccessful();
        $this->artisan('pa:send-email-reminders')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertNotNull($appointment->refresh()->term_reminded_at);
    }

    public function test_voters_hear_when_a_vote_opens_and_non_voters_hear_it_is_closing(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_vote'])]);
        $admin = User::factory()->admin()->create();
        $voter = User::factory()->viewer()->create(['email' => 'pengundi@example.com']);
        $vote = Vote::query()->create([
            'title' => 'Pemilihan AJK 2027',
            'created_by_user_id' => $admin->id,
            'opens_at' => now()->subMinute(),
            'closes_at' => now()->addHours(10),
        ]);
        $vote->voters()->attach($voter->id);

        $this->artisan('pa:send-email-reminders')->assertSuccessful();

        Http::assertSent(fn (HttpRequest $request): bool => $request['to'] === ['pengundi@example.com']
            && str_contains((string) $request['subject'], 'Pemilihan AJK 2027'));
        $this->assertNotNull($vote->refresh()->opened_notified_at);
        $this->assertNotNull($vote->closing_notified_at);
    }

    public function test_the_weekly_digest_goes_to_management_with_the_waiting_applications(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_digest'])]);
        $admin = User::factory()->admin()->create(['email' => 'pentadbir@example.com']);
        User::factory()->viewer()->create(['email' => 'staf@example.com']);
        Member::factory()->create(['applicant_name' => 'Ali bin Abu', 'business_name' => 'Kedai Ali']);

        $this->artisan('pa:send-management-digest')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => $request['to'] === [$admin->email]
            && str_contains((string) $request['html'], 'Ali bin Abu'));
    }

    public function test_an_invitation_sent_to_an_address_is_e_mailed_with_its_link(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_invite'])]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.api.invitations.store'), [
                'recipient_email' => 'bakal@example.com',
                'expires_in_days' => 7,
                'max_uses' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('emailed', true);

        $this->assertStringContainsString('/register/', (string) $response->json('data.url'));
        Http::assertSent(fn (HttpRequest $request): bool => $request['to'] === ['bakal@example.com']
            && str_contains((string) $request['html'], '/register/'));
        $this->assertNotNull(Invitation::query()->where('recipient_email', 'bakal@example.com')->first());
    }

    public function test_deactivating_and_reactivating_an_account_are_e_mailed(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_active'])]);
        $superadmin = User::factory()->superadmin()->create();
        $staff = User::factory()->viewer()->create(['email' => 'staf@example.com', 'is_active' => true]);

        $base = ['name' => $staff->name, 'email' => $staff->email, 'role' => 'committee', 'can_invite' => false, 'account_position_id' => null];

        app(AccountService::class)->update($superadmin, $staff, $base + ['is_active' => false]);
        Http::assertSent(fn (HttpRequest $request): bool => $request['to'] === ['staf@example.com']
            && str_contains((string) $request['subject'], 'deactivated'));

        app(AccountService::class)->update($superadmin, $staff->refresh(), $base + ['is_active' => true]);
        Http::assertSentCount(2);
    }

    public function test_announcements_reach_only_consenting_verified_members_and_can_be_stopped_with_one_link(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_announce'])]);
        $admin = User::factory()->superadmin()->create();

        $eligible = Member::factory()->approved()->create([
            'email' => 'ahli@example.com',
            'email_verified_at' => now(),
            'announcement_consent_at' => now(),
        ]);
        Member::factory()->approved()->create(['email' => 'tanpa@example.com', 'email_verified_at' => now(), 'announcement_consent_at' => null]);
        Member::factory()->approved()->create(['email' => 'belum@example.com', 'email_verified_at' => null, 'announcement_consent_at' => now()]);

        $this->actingAs($admin)
            ->getJson(route('admin.api.announcements.recipients'))
            ->assertOk()
            ->assertJsonPath('data.count', 1);

        $this->actingAs($admin)
            ->postJson(route('admin.api.announcements.store'), [
                'subject' => 'Mesyuarat agung',
                'body' => "Mesyuarat agung tahunan diadakan pada 15 Januari.\n\nSila hadir.",
            ])
            ->assertOk()
            ->assertJsonPath('data.sent', 1);

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => $request['to'] === ['ahli@example.com']);

        $html = (string) Http::recorded()[0][0]->data()['html'];
        preg_match('#https?://[^"\s<]*announcements/unsubscribe[^"\s<]*#', $html, $matches);
        $this->assertNotEmpty($matches);

        $this->get(html_entity_decode($matches[0]))->assertOk()->assertSee('Langganan dibatalkan');
        $this->assertNull($eligible->refresh()->announcement_consent_at);
    }

    public function test_announcements_are_refused_while_resend_is_not_connected(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.announcements.store'), ['subject' => 'Hai', 'body' => 'Ujian'])
            ->assertUnprocessable();
    }

    public function test_an_unsigned_unsubscribe_link_is_refused(): void
    {
        $member = Member::factory()->approved()->create(['announcement_consent_at' => now()]);

        $this->get(route('announcements.unsubscribe', ['member' => $member->id]))->assertForbidden();
        $this->assertNotNull($member->refresh()->announcement_consent_at);
    }

    private function request(string $userAgent, string $ip): Request
    {
        return Request::create('/login', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => $userAgent,
            'REMOTE_ADDR' => $ip,
        ]);
    }

    private function connectResend(): void
    {
        $settings = app(IntegrationSettings::class);
        $settings->put('resend.api_key', 're_live_key', null);
        $settings->put('resend.from_email', 'info@example.com', null);
        $settings->markVerified('resend', null);
    }
}
