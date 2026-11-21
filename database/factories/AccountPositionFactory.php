<?php

namespace Database\Factories;

use App\Models\AccountPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountPosition>
 */
class AccountPositionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_bm' => fake()->unique()->words(2, true),
            'name_en' => fake()->words(2, true),
            'level' => fake()->unique()->numberBetween(100, 999),
            'is_active' => true,
        ];
    }
}
