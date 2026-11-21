<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state: an active staff account with the committee role.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Committee,
            'account_type' => AccountType::Staff,
            'can_invite' => false,
            'is_active' => true,
            'account_position_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Create an admin account. Admins are created by the superadmin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => ['account_type' => AccountType::Admin]);
    }

    /**
     * Create the superadmin (developer) account.
     */
    public function superadmin(): static
    {
        return $this->state(fn (array $attributes): array => ['account_type' => AccountType::Superadmin]);
    }

    /**
     * Create a read-only viewer staff account.
     */
    public function viewer(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => UserRole::Viewer]);
    }

    /**
     * Create a deactivated account that must not be able to log in.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    /**
     * Allow a staff account to create invitations.
     */
    public function canInvite(): static
    {
        return $this->state(fn (array $attributes): array => ['can_invite' => true]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
