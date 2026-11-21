<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAllAccountsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'kata-laluan-kuat-123';

    public function test_every_account_type_gets_the_warning_and_the_security_settings(): void
    {
        $accounts = [
            User::factory()->viewer()->create(),
            User::factory()->create(['account_type' => 'staff', 'role' => 'committee']),
            User::factory()->admin()->create(),
            User::factory()->superadmin()->create(),
        ];

        foreach ($accounts as $user) {

            $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->assertSee('data-two-factor-warning', false);
            $this->actingAs($user)->get(route('admin.profile.index'))->assertOk();
            $this->actingAs($user)->getJson(route('admin.api.profile.two-factor.show'))->assertOk()->assertJsonPath('data.authenticator.enabled', false);
        }
    }

    public function test_viewer_can_sign_in_with_the_authenticator_second_step(): void
    {
        $secret = app(Totp::class)->generateSecret();
        $viewer = User::factory()->viewer()->create([
            'password' => self::PASSWORD,
            'two_factor_authenticator_secret' => $secret,
            'two_factor_authenticator_confirmed_at' => now(),
        ]);

        $this->post(route('login'), ['email' => $viewer->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('login.two-factor'));
        $this->post(route('login.two-factor.method'), ['method' => 'authenticator']);
        $this->post(route('login.two-factor.verify'), [
            'code' => app(Totp::class)->codeForStep($secret, intdiv(now()->timestamp, Totp::PERIOD)),
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($viewer);
    }
}
