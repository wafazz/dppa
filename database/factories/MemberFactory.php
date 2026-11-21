<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\BusinessCategory;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $description = fake()->sentence(12);
        $needs = fake()->sentence(10);

        return [
            'member_no' => null,
            'applicant_name' => fake()->name(),
            'business_name' => fake()->company(),
            'business_category_id' => BusinessCategory::factory(),
            'business_address' => fake()->address(),
            'phone' => '01'.fake()->numberBetween(2, 9).fake()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'ssm_no' => fake()->unique()->numerify('##########-X'),
            'business_description' => '<p>'.$description.'</p>',
            'business_needs' => '<p>'.$needs.'</p>',
            'search_text' => $description.' '.$needs,
            'status' => MemberStatus::Pending,
            'applied_at' => now(),
            'approved_at' => null,
            'rejected_at' => null,
            'consent_given_at' => now(),
            'consent_version' => config('membership.consent_version'),
        ];
    }

    /**
     * Create an approved member with a member number.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'member_no' => 'DPPA-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'status' => MemberStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    /**
     * Create a rejected application.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MemberStatus::Rejected,
            'rejected_at' => now(),
        ]);
    }
}
