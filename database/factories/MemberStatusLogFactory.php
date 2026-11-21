<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberStatusLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberStatusLog>
 */
class MemberStatusLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'from_status' => null,
            'to_status' => MemberStatus::Pending,
            'changed_by_user_id' => null,
            'note' => null,
        ];
    }
}
