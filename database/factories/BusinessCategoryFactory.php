<?php

namespace Database\Factories;

use App\Models\BusinessCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessCategory>
 */
class BusinessCategoryFactory extends Factory
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

    /**
     * Create an inactive category that can no longer be selected.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
