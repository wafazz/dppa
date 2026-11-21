<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(64),
            'created_by_user_id' => User::factory()->admin(),
            'note' => fake()->sentence(3),
            'expires_at' => now()->addDays(7),
            'max_uses' => 1,
            'used_count' => 0,
            'revoked_at' => null,
        ];
    }

    /**
     * An invitation whose expiry date has passed.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subDay()]);
    }

    /**
     * An invitation that an administrator revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => ['revoked_at' => now()]);
    }
}
