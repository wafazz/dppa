<?php

namespace Database\Factories;

use App\Models\FeeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeType>
 */
class FeeTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'fee-'.fake()->unique()->numerify('####'),
            'name_bm' => 'Yuran tahunan',
            'name_en' => 'Annual fee',
            'description' => null,
            'amount_cents' => 5000,
            'frequency' => FeeType::FREQUENCY_YEARLY,
            'business_category_id' => null,
            'due_days' => 30,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn (): array => ['frequency' => FeeType::FREQUENCY_MONTHLY]);
    }

    public function lifetime(): static
    {
        return $this->state(fn (): array => ['frequency' => FeeType::FREQUENCY_LIFETIME]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
