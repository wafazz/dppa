<?php

namespace Database\Factories;

use App\Models\ChamberPosition;
use App\Models\Member;
use App\Models\PositionAppointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PositionAppointment>
 */
class PositionAppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $termStart = fake()->dateTimeBetween('-2 years', 'now');

        return [
            'member_id' => Member::factory()->approved(),
            'chamber_position_id' => ChamberPosition::factory(),
            'term_start' => $termStart,
            'term_end' => null,
            'is_current' => false,
            'notes' => null,
            'created_by_user_id' => null,
        ];
    }

    /**
     * Mark the appointment as the current term.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes): array => [
            'term_start' => now()->subMonth()->toDateString(),
            'term_end' => now()->addYear()->toDateString(),
            'is_current' => true,
        ]);
    }
}
