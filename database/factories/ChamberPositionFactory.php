<?php

namespace Database\Factories;

use App\Models\ChamberPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChamberPosition>
 */
class ChamberPositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_bm' => fake()->unique()->words(2, true),
            'name_en' => fake()->words(2, true),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 99),
        ];
    }
}
