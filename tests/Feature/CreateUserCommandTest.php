<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_an_admin_account_with_a_hashed_password(): void
    {
        $this->artisan('pa:create-user', [
            '--name' => 'Pentadbir Dewan',
            '--email' => 'Admin@Example.com',
            '--account-type' => 'admin',
            '--password' => 'kata-laluan-selamat-123',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'admin@example.com')->sole();

        $this->assertSame(AccountType::Admin, $user->account_type);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('kata-laluan-selamat-123', $user->password));
        $this->assertNotSame('kata-laluan-selamat-123', $user->password);
    }

    public function test_command_creates_the_superadmin_developer_account(): void
    {
        $this->artisan('pa:create-user', [
            '--name' => 'Pembangun',
            '--email' => 'dev@example.com',
            '--account-type' => 'superadmin',
            '--password' => 'kata-laluan-selamat-123',
        ])->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'dev@example.com')->sole()->isSuperadmin());
    }

    public function test_staff_account_receives_the_requested_access_role(): void
    {
        $this->artisan('pa:create-user', [
            '--name' => 'Pemerhati Uji',
            '--email' => 'pemerhati@example.com',
            '--account-type' => 'staff',
            '--role' => 'viewer',
            '--password' => 'kata-laluan-selamat-123',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'pemerhati@example.com')->sole();

        $this->assertSame(UserRole::Viewer, $user->role);
        $this->assertTrue($user->isStaff());
    }

    public function test_command_rejects_duplicate_email_addresses(): void
    {
        User::factory()->create(['email' => 'ahli@example.com']);

        $this->artisan('pa:create-user', [
            '--name' => 'Ahli Baharu',
            '--email' => 'ahli@example.com',
            '--account-type' => 'staff',
            '--role' => 'committee',
            '--password' => 'kata-laluan-selamat-123',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_command_rejects_short_passwords_and_unknown_roles(): void
    {
        $this->artisan('pa:create-user', [
            '--name' => 'Ahli',
            '--email' => 'pendek@example.com',
            '--account-type' => 'staff',
            '--role' => 'committee',
            '--password' => 'pendek',
        ])->assertFailed();

        $this->artisan('pa:create-user', [
            '--name' => 'Ahli',
            '--email' => 'peranan@example.com',
            '--account-type' => 'staff',
            '--role' => 'superuser',
            '--password' => 'kata-laluan-selamat-123',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
