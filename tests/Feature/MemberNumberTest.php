<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Services\MemberNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_numbers_are_sequential_within_a_year(): void
    {
        $generator = app(MemberNumberGenerator::class);

        $this->assertSame('DPPA-2026-0001', $generator->generate(2026));
        $this->assertSame('DPPA-2026-0002', $generator->generate(2026));
        $this->assertSame('DPPA-2026-0003', $generator->generate(2026));
    }

    public function test_numbering_restarts_for_a_new_year_without_affecting_others(): void
    {
        $generator = app(MemberNumberGenerator::class);

        $generator->generate(2026);
        $generator->generate(2026);

        $this->assertSame('DPPA-2027-0001', $generator->generate(2027));
        $this->assertSame('DPPA-2026-0003', $generator->generate(2026));
    }

    public function test_every_approved_member_receives_a_distinct_number(): void
    {
        $committee = User::factory()->create();
        $members = Member::factory()->count(8)->create();

        foreach ($members as $member) {
            $this->actingAs($committee)
                ->postJson(route('admin.api.members.review', $member), ['status' => 'approved'])
                ->assertOk();
        }

        $numbers = Member::query()->pluck('member_no')->all();

        $this->assertCount(8, $numbers);
        $this->assertCount(8, array_unique($numbers));
        $this->assertContains('DPPA-'.now()->year.'-0008', $numbers);
    }
}
