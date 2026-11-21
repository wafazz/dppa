<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\IntegrationSettings;
use App\Services\ResendMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_management_accounts_can_open_the_settings(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs(User::factory()->viewer()->create())->getJson(route('admin.api.settings.show'))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.api.settings.show'))->assertOk();
        $this->actingAs(User::factory()->superadmin()->create())->get(route('admin.settings.index'))->assertOk();
    }

    public function test_the_resend_card_saves_together_and_leaves_the_onsend_card_alone(): void
    {
        $admin = User::factory()->admin()->create();
        $this->saveCard($admin, 'onsend', ['api_key' => 'onsend_key', 'base_url' => 'https://api.onsend.example', 'sender_id' => 'DPPA']);

        $this->saveCard($admin, 'resend', ['api_key' => 're_live_key', 'from_email' => 'info@example.com', 'from_name' => 'Dewan PA']);

        $settings = app(IntegrationSettings::class);
        $this->assertSame('re_live_key', $settings->get('resend.api_key'));
        $this->assertSame('info@example.com', $settings->get('resend.from_email'));
        $this->assertSame('Dewan PA', $settings->get('resend.from_name'));
        $this->assertSame('onsend_key', $settings->get('onsend.api_key'));
        $this->assertSame('https://api.onsend.example', $settings->get('onsend.base_url'));
        $this->assertSame('DPPA', $settings->get('onsend.sender_id'));
    }

    public function test_api_keys_are_encrypted_at_rest_and_never_returned(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->saveCard($admin, 'resend', ['api_key' => 're_secret_123456'])
            ->assertJsonPath('data.resend.has_api_key', true);

        $this->assertStringNotContainsString('re_secret_123456', $response->getContent());

        $stored = AppSetting::query()->whereKey('resend.api_key')->sole();
        $this->assertTrue($stored->is_secret);
        $this->assertStringNotContainsString('re_secret_123456', $stored->value);
        $this->assertSame('re_secret_123456', app(IntegrationSettings::class)->get('resend.api_key'));
    }

    public function test_a_blank_api_key_keeps_the_saved_key_while_other_fields_update(): void
    {
        $admin = User::factory()->admin()->create();
        $this->saveCard($admin, 'resend', ['api_key' => 're_original_key', 'from_email' => 'a@example.com']);

        $this->saveCard($admin, 'resend', ['api_key' => null, 'from_email' => 'b@example.com']);

        $settings = app(IntegrationSettings::class);
        $this->assertSame('re_original_key', $settings->get('resend.api_key'));
        $this->assertSame('b@example.com', $settings->get('resend.from_email'));
    }

    public function test_a_blank_plain_field_clears_that_value_on_save(): void
    {
        $admin = User::factory()->admin()->create();
        $this->saveCard($admin, 'onsend', ['sender_id' => 'DPPA', 'base_url' => 'https://api.onsend.example']);

        $this->saveCard($admin, 'onsend', ['sender_id' => null, 'base_url' => 'https://api.onsend.example']);

        $settings = app(IntegrationSettings::class);
        $this->assertNull($settings->get('onsend.sender_id'));
        $this->assertSame('https://api.onsend.example', $settings->get('onsend.base_url'));
    }

    public function test_invalid_fields_are_reported_by_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson(route('admin.api.settings.update', ['card' => 'resend']), ['from_email' => 'bukan-emel'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from_email');

        $this->actingAs($admin)
            ->putJson(route('admin.api.settings.update', ['card' => 'onsend']), ['base_url' => 'http://insecure.example'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('base_url');
    }

    public function test_unknown_cards_are_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('admin.api.settings.update', ['card' => 'database']), ['password' => 'x'])
            ->assertNotFound();
    }

    public function test_staff_cannot_save_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->putJson(route('admin.api.settings.update', ['card' => 'resend']), ['from_email' => 'a@example.com'])
            ->assertForbidden();
    }

    public function test_the_resend_test_sends_an_email_through_the_resend_api(): void
    {
        Http::fake(['https://api.resend.com/emails' => Http::response(['id' => 'msg_123'], 200)]);

        $admin = User::factory()->superadmin()->create();
        $this->saveCard($admin, 'resend', ['api_key' => 're_live_key', 'from_email' => 'info@example.com', 'from_name' => 'Dewan PA']);

        $this->actingAs($admin)
            ->postJson(route('admin.api.settings.test-resend'), ['to' => 'penerima@example.com'])
            ->assertOk()
            ->assertJsonPath('id', 'msg_123');

        Http::assertSent(function ($request): bool {
            return $request->url() === ResendMailer::ENDPOINT
                && $request->hasHeader('Authorization', 'Bearer re_live_key')
                && $request['from'] === 'Dewan PA <info@example.com>'
                && $request['to'] === ['penerima@example.com']
                && str_contains($request['html'], 'test');
        });
    }

    public function test_the_resend_test_reports_when_resend_is_not_configured(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.settings.test-resend'), ['to' => 'penerima@example.com'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Resend is not configured yet. Enter the API key and sender email first.');

        Http::assertNothingSent();
    }

    public function test_a_resend_rejection_is_shown_to_the_administrator(): void
    {
        Http::fake(['https://api.resend.com/emails' => Http::response(['message' => 'The from address is not verified'], 403)]);

        $admin = User::factory()->admin()->create();
        $this->saveCard($admin, 'resend', ['api_key' => 're_key', 'from_email' => 'belum@example.com']);

        $this->actingAs($admin)
            ->postJson(route('admin.api.settings.test-resend'), ['to' => 'penerima@example.com'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Sending failed: The from address is not verified');
    }

    /**
     * Save one card's fields through the card endpoint.
     *
     * @param  array<string, mixed>  $fields
     */
    private function saveCard(User $user, string $card, array $fields): TestResponse
    {
        return $this->actingAs($user)
            ->putJson(route('admin.api.settings.update', ['card' => $card]), $fields)
            ->assertOk();
    }
}
