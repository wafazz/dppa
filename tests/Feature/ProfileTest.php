<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_account_type_can_open_the_profile_page(): void
    {
        foreach ([User::factory()->superadmin(), User::factory()->admin(), User::factory()->viewer()] as $factory) {
            $this->actingAs($factory->create())
                ->get(route('admin.profile.index'))
                ->assertOk()
                ->assertSee('data-vue="profile-index"', false);
        }
    }

    public function test_name_can_be_changed_but_email_cannot(): void
    {
        $user = User::factory()->viewer()->create(['email' => 'kekal@example.com']);

        $this->actingAs($user)
            ->putJson(route('admin.api.profile.update'), ['name' => 'Nama Baharu', 'address_line1' => 'No. 1, Jalan Dewan', 'email' => 'lain@example.com'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Baharu')
            ->assertJsonPath('data.email', 'kekal@example.com');

        $this->assertSame('Nama Baharu', $user->fresh()->name);
        $this->assertSame('kekal@example.com', $user->fresh()->email);
    }

    public function test_password_change_needs_the_correct_current_password(): void
    {
        $user = User::factory()->admin()->create(['password' => 'kata-laluan-lama-123']);

        $this->actingAs($user)
            ->putJson(route('admin.api.profile.password'), [
                'current_password' => 'salah-sekali-ini',
                'password' => 'kata-laluan-baharu-456',
                'password_confirmation' => 'kata-laluan-baharu-456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('kata-laluan-lama-123', $user->fresh()->password));
    }

    public function test_password_change_checks_confirmation_length_and_difference(): void
    {
        $user = User::factory()->admin()->create(['password' => 'kata-laluan-lama-123']);

        $this->actingAs($user)
            ->putJson(route('admin.api.profile.password'), [
                'current_password' => 'kata-laluan-lama-123',
                'password' => 'kata-laluan-baharu-456',
                'password_confirmation' => 'tidak-sama-langsung',
            ])
            ->assertJsonValidationErrors('password');

        $this->actingAs($user)
            ->putJson(route('admin.api.profile.password'), [
                'current_password' => 'kata-laluan-lama-123',
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ])
            ->assertJsonValidationErrors('password');

        $this->actingAs($user)
            ->putJson(route('admin.api.profile.password'), [
                'current_password' => 'kata-laluan-lama-123',
                'password' => 'kata-laluan-lama-123',
                'password_confirmation' => 'kata-laluan-lama-123',
            ])
            ->assertJsonValidationErrors('password');
    }

    public function test_valid_password_change_updates_the_hash(): void
    {
        $user = User::factory()->superadmin()->create(['password' => 'kata-laluan-lama-123']);

        $this->actingAs($user)
            ->putJson(route('admin.api.profile.password'), [
                'current_password' => 'kata-laluan-lama-123',
                'password' => 'kata-laluan-baharu-456',
                'password_confirmation' => 'kata-laluan-baharu-456',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('kata-laluan-baharu-456', $user->fresh()->password));
    }

    public function test_password_change_ends_the_other_sessions_of_the_account(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->admin()->create(['password' => 'kata-laluan-lama-123']);
        $other = User::factory()->admin()->create();

        DB::table('sessions')->insert([
            ['id' => 'sesi-lain', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => time()],
            ['id' => 'sesi-akaun-lain', 'user_id' => $other->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($user)
            ->putJson(route('admin.api.profile.password'), [
                'current_password' => 'kata-laluan-lama-123',
                'password' => 'kata-laluan-baharu-456',
                'password_confirmation' => 'kata-laluan-baharu-456',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-lain']);
        $this->assertDatabaseHas('sessions', ['id' => 'sesi-akaun-lain']);
    }

    public function test_square_png_becomes_the_display_picture(): void
    {
        Storage::fake('public');
        $user = User::factory()->viewer()->create();

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('gambar.png', 256, 256)])
            ->assertOk()
            ->assertJsonPath('data.avatar_url', fn (string $url): bool => str_contains($url, 'avatars/user-'.$user->id.'.png'));

        $this->assertNotNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertExists('avatars/user-'.$user->id.'.png');
    }

    public function test_display_picture_must_be_a_square_png_of_sensible_size(): void
    {
        Storage::fake('public');
        $user = User::factory()->viewer()->create();

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('lebar.png', 300, 200)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('kecil.png', 32, 32)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('gambar.jpg', 128, 128)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_display_picture_can_be_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->superadmin()->create();

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('gambar.png', 128, 128)])
            ->assertOk();

        $this->actingAs($user)
            ->deleteJson(route('admin.api.profile.avatar.destroy'))
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);

        $this->assertNull($user->fresh()->avatar_path);
    }
}
