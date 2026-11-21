<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteReceipt;
use App\Services\ActivityLogger;
use App\Services\MemberStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An approved member and the login account created on approval.
     *
     * @return array{member: Member, account: User}
     */
    private function approvedMember(string $email): array
    {
        $member = Member::factory()->create(['email' => $email, 'applicant_name' => 'Ali bin Abu']);
        app(MemberStatusService::class)->changeStatus($member, MemberStatus::Approved, User::factory()->superadmin()->create(), null);

        return [
            'member' => $member->refresh(),
            'account' => User::query()->where('member_id', $member->id)->firstOrFail(),
        ];
    }

    private function openVote(string $title, User $voter): Vote
    {
        $vote = Vote::query()->create([
            'title' => $title,
            'created_by_user_id' => User::factory()->admin()->create()->id,
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addDay(),
        ]);
        $vote->voters()->attach($voter->id);

        return $vote;
    }

    public function test_a_member_has_a_dashboard_of_their_own_membership_and_votes(): void
    {
        ['member' => $member, 'account' => $account] = $this->approvedMember('ali@example.com');
        $waiting = $this->openVote('Pemilihan AJK 2027', $account);

        $this->actingAs($account)
            ->get(route('member.home'))
            ->assertOk()
            ->assertSee(__('app.member_dashboard.welcome', ['name' => 'Ali bin Abu']))
            ->assertSee($member->member_no)
            ->assertSee('Pemilihan AJK 2027')
            ->assertSee(route('member.votes.index', ['status' => 'open']), false)
            ->assertSee(route('member.activity.index'), false);

        $this->assertNotNull($waiting);
    }

    public function test_votes_already_cast_are_not_counted_as_waiting(): void
    {
        ['account' => $account] = $this->approvedMember('cast@example.com');
        $voted = $this->openVote('Sudah mengundi', $account);
        $this->openVote('Belum mengundi', $account);

        VoteReceipt::query()->create(['vote_id' => $voted->id, 'user_id' => $account->id, 'cast_at' => now()]);

        $page = $this->actingAs($account)->get(route('member.home'))->assertOk();

        $this->assertSame(1, $this->dashboardWaitingCount($page));
        $page->assertDontSee('Sudah mengundi');
    }

    public function test_the_member_sees_only_their_own_recent_activity(): void
    {
        ['account' => $account] = $this->approvedMember('sendiri@example.com');
        ['account' => $other] = $this->approvedMember('lain@example.com');
        $logger = app(ActivityLogger::class);
        $logger->record('vote.cast', null, $account, null);
        $logger->record('member.exported', null, $other, null);

        $this->actingAs($account)
            ->get(route('member.home'))
            ->assertOk()
            // Match the list markup: every translation is also embedded in the page's script data.
            ->assertSee('<span>'.__('app.activity.events.vote.cast').'</span>', false)
            ->assertDontSee('<span>'.__('app.activity.events.member.exported').'</span>', false);
    }

    public function test_committee_accounts_are_not_shown_the_member_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('member.home'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_the_sidebar_dashboard_link_leads_a_member_to_the_member_area(): void
    {
        ['account' => $account] = $this->approvedMember('menu@example.com');

        $this->actingAs($account)
            ->get(route('member.home'))
            ->assertSee('href="'.route('member.home').'"', false)
            ->assertDontSee('href="'.route('admin.dashboard').'"', false);
    }

    /**
     * The waiting-votes count is shown in the first card, as the number after the label.
     */
    private function dashboardWaitingCount($page): int
    {
        $html = $page->getContent();
        preg_match('/'.preg_quote(__('app.member_dashboard.waiting'), '/').'<\/div>\s*<div class="fs-4 fw-semibold">(\d+)<\/div>/', $html, $matches);

        return (int) ($matches[1] ?? -1);
    }
}
