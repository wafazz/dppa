<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\User;
use App\Services\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'kata-laluan-kuat-123';

    public function test_sign_ins_and_sign_outs_are_recorded_with_the_account(): void
    {
        $user = User::factory()->admin()->create(['password' => self::PASSWORD]);

        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post(route('logout'));

        $this->assertDatabaseHas('activity_logs', ['event' => 'auth.login', 'user_id' => $user->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_failed_sign_ins_are_recorded_with_the_email_tried(): void
    {
        $this->post(route('login'), ['email' => 'tiada@example.com', 'password' => 'salah-sekali-123']);

        $entry = ActivityLog::query()->where('event', 'auth.login_failed')->firstOrFail();
        $this->assertNull($entry->user_id);
        $this->assertSame('tiada@example.com', $entry->properties['email']);
    }

    public function test_changes_to_records_keep_old_and_new_values_but_never_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $admin->forceFill(['name' => 'Nama Baharu', 'password' => self::PASSWORD, 'two_factor_authenticator_secret' => 'RAHSIA'])->save();

        $entry = ActivityLog::query()->where('event', 'account.updated')->where('subject_id', $admin->id)->firstOrFail();
        $this->assertSame('Nama Baharu', $entry->properties['changes']['name']['new']);
        $this->assertArrayNotHasKey('password', $entry->properties['changes']);
        $this->assertArrayNotHasKey('two_factor_authenticator_secret', $entry->properties['changes']);
        $this->assertStringNotContainsString('RAHSIA', (string) $entry->getRawOriginal('properties'));
    }

    public function test_secret_settings_are_redacted_in_the_trail(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        app(IntegrationSettings::class)->put('resend.api_key', 're_rahsia_123', $admin->id);

        $entry = ActivityLog::query()->where('event', 'setting.created')->where('subject_id', 'resend.api_key')->firstOrFail();
        $this->assertSame('[redacted]', $entry->properties['changes']['value']);
        $this->assertStringNotContainsString('re_rahsia_123', (string) $entry->getRawOriginal('properties'));
    }

    public function test_invitation_tokens_are_not_recorded(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $invitation = Invitation::factory()->create(['created_by_user_id' => $admin->id, 'token' => 'token-rahsia-xyz']);

        $entry = ActivityLog::query()->where('event', 'invitation.created')->where('subject_id', $invitation->id)->firstOrFail();
        $this->assertStringNotContainsString('token-rahsia-xyz', (string) $entry->getRawOriginal('properties'));
    }

    public function test_management_sees_every_account_and_can_filter_by_one(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Satu', 'password' => self::PASSWORD]);
        $viewer = User::factory()->viewer()->create(['name' => 'Penonton Dua', 'password' => self::PASSWORD]);

        $this->post(route('login'), ['email' => $viewer->email, 'password' => self::PASSWORD]);
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $admin->email, 'password' => self::PASSWORD]);

        $this->get(route('admin.activity.index'))
            ->assertOk()
            ->assertSee('Penonton Dua')
            ->assertSee('Admin Satu');

        // The account filter lists every account, so count the sign-in rows inside the table only.
        $this->assertSame(2, $this->signInRows($this->get(route('admin.activity.index'))->getContent()));

        $filtered = $this->get(route('admin.activity.index', ['user_id' => $viewer->id]))->assertOk()->getContent();
        $this->assertSame(1, $this->signInRows($filtered));
    }

    public function test_other_accounts_see_only_their_own_activity(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Satu', 'password' => self::PASSWORD]);
        $viewer = User::factory()->viewer()->create(['name' => 'Penonton Dua', 'password' => self::PASSWORD]);

        $this->post(route('login'), ['email' => $admin->email, 'password' => self::PASSWORD]);
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $viewer->email, 'password' => self::PASSWORD]);

        $this->get(route('admin.activity.index'))
            ->assertOk()
            ->assertSee('Penonton Dua')
            ->assertDontSee('Admin Satu');

        $this->get(route('admin.activity.index', ['user_id' => $admin->id]))
            ->assertOk()
            ->assertDontSee('Admin Satu');
    }

    private function signInRows(string $html): int
    {
        preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $matches);

        return substr_count($matches[1] ?? '', 'Signed in');
    }
}
