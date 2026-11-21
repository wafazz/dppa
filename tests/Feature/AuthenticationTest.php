<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available_to_guests(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Log Masuk Jawatankuasa');
    }

    public function test_valid_credentials_log_the_committee_user_in(): void
    {
        $user = User::factory()->create(['email' => 'setiausaha@example.com']);

        $this->post(route('login'), ['email' => 'setiausaha@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected_with_bahasa_message(): void
    {
        User::factory()->create(['email' => 'bendahari@example.com']);

        $this->from(route('login'))
            ->post(route('login'), ['email' => 'bendahari@example.com', 'password' => 'salah-sekali'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Emel atau kata laluan tidak sepadan.']);

        $this->assertGuest();
    }

    public function test_admin_pages_redirect_guests_to_login(): void
    {
        $this->get(route('admin.members.index'))->assertRedirect(route('login'));
        $this->get(route('admin.positions.index'))->assertRedirect(route('login'));
        $this->get(route('admin.categories.index'))->assertRedirect(route('login'));
    }

    public function test_api_rejects_guests_with_unauthenticated_response(): void
    {
        $this->getJson(route('admin.api.members.index'))->assertUnauthorized();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        User::factory()->create(['email' => 'pemerhati@example.com']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), ['email' => 'pemerhati@example.com', 'password' => 'salah']);
        }

        $this->post(route('login'), ['email' => 'pemerhati@example.com', 'password' => 'salah'])
            ->assertStatus(429);
    }
}
