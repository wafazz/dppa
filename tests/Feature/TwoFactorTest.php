<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IntegrationSettings;
use App\Services\Totp;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'kata-laluan-kuat-123';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * An account with the authenticator app switched on, and the secret it uses.
     *
     * @return array{0: User, 1: string}
     */
    private function userWithAuthenticator(): array
    {
        $secret = app(Totp::class)->generateSecret();
        $user = User::factory()->admin()->create([
            'password' => self::PASSWORD,
            'two_factor_authenticator_secret' => $secret,
            'two_factor_authenticator_confirmed_at' => now(),
        ]);

        return [$user, $secret];
    }

    private function currentCode(string $secret): string
    {
        return app(Totp::class)->codeForStep($secret, intdiv(now()->timestamp, Totp::PERIOD));
    }

    private function connectResend(): void
    {
        $settings = app(IntegrationSettings::class);
        $settings->put('resend.api_key', 're_test_key', null);
        $settings->put('resend.from_email', 'no-reply@example.com', null);
        $settings->markVerified('resend', null);
    }

    public function test_totp_matches_the_rfc_6238_test_vector(): void
    {
        $totp = app(Totp::class);
        $secret = $totp->base32Encode('12345678901234567890');

        // RFC 6238 gives 94287082 for the 8-digit code at time 59; the 6-digit form is 287082.
        $this->assertSame('287082', $totp->codeForStep($secret, 1));
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        [$user, $secret] = $this->userWithAuthenticator();
        $service = app(TwoFactorService::class);
        $code = $this->currentCode($secret);

        $this->assertTrue($service->verifyAuthenticator($user, $code));
        $this->assertFalse($service->verifyAuthenticator($user->fresh(), $code));
    }

    public function test_password_alone_is_not_enough_when_the_authenticator_is_on(): void
    {
        [$user] = $this->userWithAuthenticator();

        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('login.two-factor'));

        $this->assertGuest();
    }

    public function test_correct_authenticator_code_signs_the_account_in(): void
    {
        [$user, $secret] = $this->userWithAuthenticator();

        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post(route('login.two-factor.method'), ['method' => 'authenticator'])
            ->assertRedirect(route('login.two-factor'));
        $this->post(route('login.two-factor.verify'), ['code' => $this->currentCode($secret)])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_second_step_shows_six_digit_boxes_instead_of_a_text_field(): void
    {
        [$user] = $this->userWithAuthenticator();

        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post(route('login.two-factor.method'), ['method' => 'authenticator']);

        $this->get(route('login.two-factor'))
            ->assertOk()
            ->assertSee('data-pin-pad', false)
            ->assertSee('name="code"', false)
            ->assertSee('data-pin-box', false);
    }

    public function test_wrong_codes_are_refused_and_five_of_them_end_the_attempt(): void
    {
        [$user] = $this->userWithAuthenticator();

        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post(route('login.two-factor.method'), ['method' => 'authenticator']);

        $this->post(route('login.two-factor.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        for ($attempt = 2; $attempt <= 5; $attempt++) {
            $this->post(route('login.two-factor.verify'), ['code' => '000000']);
        }

        $this->post(route('login.two-factor.verify'), ['code' => '000000'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_email_code_is_sent_through_resend_and_signs_the_account_in(): void
    {
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'msg_2fa'])]);
        $this->connectResend();

        $user = User::factory()->admin()->create(['password' => self::PASSWORD, 'two_factor_email_enabled' => true]);

        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post(route('login.two-factor.method'), ['method' => 'email'])->assertRedirect(route('login.two-factor'));

        Http::assertSentCount(1);
        preg_match('/\b(\d{6})\b/', (string) Http::recorded()[0][0]->body(), $matches);

        $this->assertCount(2, $matches);
        $this->post(route('login.two-factor.verify'), ['code' => $matches[1]])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_two_factor_account_is_refused_when_none_of_its_methods_is_connected(): void
    {
        $user = User::factory()->admin()->create(['password' => self::PASSWORD, 'two_factor_email_enabled' => true]);

        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_email_codes_cannot_be_switched_on_until_resend_is_connected(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.two-factor.email.send'))
            ->assertUnprocessable();
    }

    public function test_authenticator_setup_is_switched_on_by_a_confirmed_code_and_turned_off_with_the_password(): void
    {
        $user = User::factory()->admin()->create(['password' => self::PASSWORD]);

        $setup = $this->actingAs($user)
            ->postJson(route('admin.api.profile.two-factor.authenticator.setup'))
            ->assertOk()
            ->json('data');

        $this->assertStringStartsWith('otpauth://totp/', $setup['otpauth_uri']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $setup['qr_data_uri']);
        $this->assertStringContainsString('<svg', base64_decode(substr($setup['qr_data_uri'], strlen('data:image/svg+xml;base64,'))));
        $this->assertFalse($user->fresh()->two_factor_authenticator_confirmed_at !== null);

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.two-factor.authenticator.confirm'), ['code' => '000000'])
            ->assertJsonValidationErrors('code');

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.two-factor.authenticator.confirm'), ['code' => $this->currentCode($setup['secret'])])
            ->assertOk();

        $this->assertNotNull($user->fresh()->two_factor_authenticator_confirmed_at);

        // Turning the app off needs a code from the app; the password is not enough.
        $this->actingAs($user)
            ->deleteJson(route('admin.api.profile.two-factor.disable', 'authenticator'), ['password' => self::PASSWORD])
            ->assertJsonValidationErrors('code');
        $this->assertNotNull($user->fresh()->two_factor_authenticator_confirmed_at);

        $this->actingAs($user)
            ->deleteJson(route('admin.api.profile.two-factor.disable', 'authenticator'), ['code' => '000000'])
            ->assertJsonValidationErrors('code');
        $this->assertNotNull($user->fresh()->two_factor_authenticator_confirmed_at);

        // The code used to confirm set-up cannot be reused, so the clock moves on to a new code.
        Carbon::setTestNow(now()->addSeconds(30));

        $this->actingAs($user)
            ->deleteJson(route('admin.api.profile.two-factor.disable', 'authenticator'), ['code' => $this->currentCode($setup['secret'])])
            ->assertOk();
        $this->assertNull($user->fresh()->two_factor_authenticator_confirmed_at);
    }

    public function test_email_codes_can_only_be_switched_off_with_a_code_sent_to_the_email(): void
    {
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'msg_off'])]);
        $this->connectResend();
        $user = User::factory()->admin()->create(['password' => self::PASSWORD, 'two_factor_email_enabled' => true]);

        $this->actingAs($user)
            ->deleteJson(route('admin.api.profile.two-factor.disable', 'email'), ['password' => self::PASSWORD])
            ->assertJsonValidationErrors('code');

        $this->actingAs($user)->postJson(route('admin.api.profile.two-factor.email.disable-code'))->assertOk();
        Http::assertSent(fn ($request) => str_contains((string) $request->body(), 'change two-factor sign-in'));
        preg_match('/\b(\d{6})\b/', (string) Http::recorded()[0][0]->body(), $matches);

        $this->actingAs($user)
            ->deleteJson(route('admin.api.profile.two-factor.disable', 'email'), ['code' => '999999'])
            ->assertJsonValidationErrors('code');
        $this->assertTrue((bool) $user->fresh()->two_factor_email_enabled);

        $this->actingAs($user)
            ->deleteJson(route('admin.api.profile.two-factor.disable', 'email'), ['code' => $matches[1]])
            ->assertOk();
        $this->assertFalse((bool) $user->fresh()->two_factor_email_enabled);
    }

    public function test_email_codes_are_switched_on_only_after_the_code_is_confirmed(): void
    {
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'msg_enable'])]);
        $this->connectResend();
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->postJson(route('admin.api.profile.two-factor.email.send'))->assertOk();
        preg_match('/\b(\d{6})\b/', (string) Http::recorded()[0][0]->body(), $matches);

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.two-factor.email.confirm'), ['code' => '999999'])
            ->assertJsonValidationErrors('code');
        $this->assertFalse((bool) $user->fresh()->two_factor_email_enabled);

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.two-factor.email.confirm'), ['code' => $matches[1]])
            ->assertOk();
        $this->assertTrue((bool) $user->fresh()->two_factor_email_enabled);
    }

    public function test_a_successful_resend_test_marks_the_channel_as_connected(): void
    {
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'msg_test'])]);
        $admin = User::factory()->admin()->create();
        $settings = app(IntegrationSettings::class);
        $settings->put('resend.api_key', 're_test_key', null);
        $settings->put('resend.from_email', 'no-reply@example.com', null);

        $this->assertFalse($settings->isConnected('resend'));

        $this->actingAs($admin)
            ->postJson(route('admin.api.settings.test-resend'), ['to' => 'ujian@example.com'])
            ->assertOk();

        $this->assertTrue($settings->isConnected('resend'));

        $settings->put('resend.from_email', 'lain@example.com', null);
        $this->assertFalse($settings->isConnected('resend'));
    }

    public function test_whatsapp_is_not_offered_because_onsend_is_not_connected(): void
    {
        $user = User::factory()->admin()->create(['two_factor_whatsapp_enabled' => true]);

        $this->assertSame([], app(TwoFactorService::class)->usableMethods($user));
        $this->assertFalse(app(IntegrationSettings::class)->isConnected('onsend'));
    }

    public function test_an_inactive_account_never_reaches_the_second_step(): void
    {
        [$user] = $this->userWithAuthenticator();
        $user->update(['is_active' => false]);

        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertFalse(Auth::check());
    }

    public function test_pages_warn_until_one_two_factor_method_is_switched_on(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-two-factor-warning', false);

        $user->forceFill(['two_factor_email_enabled' => true])->save();

        $this->actingAs($user->fresh())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('data-two-factor-warning', false);
    }
}
