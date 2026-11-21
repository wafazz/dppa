<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_assigns_unique_member_number_and_records_status_log(): void
    {
        $committee = User::factory()->create();
        $member = Member::factory()->create();

        $response = $this->actingAs($committee)
            ->postJson(route('admin.api.members.review', $member), ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.status_label', 'Approved');

        $memberNo = $response->json('data.member_no');

        $this->assertMatchesRegularExpression('/^DPPA-'.now()->year.'-\d{4}$/', $memberNo);

        $member->refresh();

        $this->assertSame(MemberStatus::Approved, $member->status);
        $this->assertSame($memberNo, $member->member_no);
        $this->assertNotNull($member->approved_at);
        $this->assertNull($member->rejected_at);

        $log = MemberStatusLog::query()->where('member_id', $member->id)->sole();

        $this->assertSame(MemberStatus::Pending, $log->from_status);
        $this->assertSame(MemberStatus::Approved, $log->to_status);
        $this->assertSame($committee->id, $log->changed_by_user_id);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.api.members.review', $member), ['status' => 'rejected', 'note' => '<p> </p>'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->assertSame(MemberStatus::Pending, $member->fresh()->status);
    }

    public function test_rejection_with_reason_sets_rejected_date_without_member_number(): void
    {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.api.members.review', $member), [
                'status' => 'rejected',
                'note' => '<p>Dokumen SSM tidak lengkap</p>',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $member->refresh();

        $this->assertNotNull($member->rejected_at);
        $this->assertNull($member->approved_at);
        $this->assertNull($member->member_no);
        $this->assertSame('<p>Dokumen SSM tidak lengkap</p>', MemberStatusLog::query()->sole()->note);
    }

    public function test_setting_the_same_status_again_is_rejected(): void
    {
        $member = Member::factory()->approved()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.api.members.review', $member), ['status' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_returning_to_pending_keeps_the_existing_member_number(): void
    {
        $member = Member::factory()->approved()->create(['member_no' => 'DPPA-2026-0042']);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.api.members.review', $member), ['status' => 'pending'])
            ->assertOk();

        $member->refresh();

        $this->assertSame('DPPA-2026-0042', $member->member_no);
        $this->assertSame(MemberStatus::Pending, $member->status);
        $this->assertNull($member->approved_at);
    }

    public function test_reapproval_keeps_the_original_member_number(): void
    {
        $member = Member::factory()->rejected()->create(['member_no' => 'DPPA-2026-0007']);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.api.members.review', $member), ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.member_no', 'DPPA-2026-0007');
    }

    public function test_status_history_is_returned_with_the_member(): void
    {
        $committee = User::factory()->create(['name' => 'Siti Setiausaha']);
        $member = Member::factory()->create();

        $this->actingAs($committee)
            ->postJson(route('admin.api.members.review', $member), ['status' => 'approved'])
            ->assertOk();

        $this->actingAs($committee)
            ->getJson(route('admin.api.members.show', $member))
            ->assertOk()
            ->assertJsonPath('data.status_logs.0.to_status', 'approved')
            ->assertJsonPath('data.status_logs.0.changed_by', 'Siti Setiausaha');
    }
}
