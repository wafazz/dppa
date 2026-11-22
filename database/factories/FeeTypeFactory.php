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
            'name_bm' => 'Yuran keahlian',
            'name_en' => 'Membership fee',
            'description' => null,
            'monthly_cents' => null,
            'yearly_cents' => 5000,
            'lifetime_cents' => null,
            'business_category_id' => null,
            'due_days' => 30,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /** Offers only a monthly price. */
    public function monthly(): static
    {
        return $this->state(fn (): array => ['monthly_cents' => 1000, 'yearly_cents' => null, 'lifetime_cents' => null]);
    }

    /** Offers only a lifetime price. */
    public function lifetime(): static
    {
        return $this->state(fn (): array => ['monthly_cents' => null, 'yearly_cents' => null, 'lifetime_cents' => 20000]);
    }

    /** Offers all three periods. */
    public function allPeriods(): static
    {
        return $this->state(fn (): array => ['monthly_cents' => 1000, 'yearly_cents' => 10000, 'lifetime_cents' => 200000]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
