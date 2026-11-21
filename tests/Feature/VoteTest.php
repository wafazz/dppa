<?php

namespace Tests\Feature;

use App\Models\AccountPosition;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteBallot;
use App\Models\VoteReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<int, User>  $voters
     * @return array<string, mixed>
     */
    private function votePayload(array $voters, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Pemilihan Jawatan Setiausaha',
            'description' => 'Undi satu calon.',
            'opens_at' => null,
            'closes_at' => '2026-11-22T18:00',
            'options' => ['Calon A', 'Calon B'],
            'voter_ids' => array_map(fn (User $user): int => $user->id, $voters),
            'account_position_ids' => [],
        ], $overrides);
    }

    private function createVote(User $creator, array $voters): Vote
    {
        $response = $this->actingAs($creator)->postJson(route('admin.api.votes.store'), $this->votePayload($voters))->assertCreated();

        return Vote::query()->findOrFail($response->json('data.id'));
    }

    public function test_only_management_and_accounts_allowed_by_management_can_set_up_votes(): void
    {
        $voter = User::factory()->viewer()->create();

        $this->actingAs(User::factory()->viewer()->create())
            ->postJson(route('admin.api.votes.store'), $this->votePayload([$voter]))
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->createVote($admin, [$voter]);

        $member = User::factory()->create(['account_type' => 'member', 'role' => null]);
        $this->actingAs($member)->postJson(route('admin.api.votes.store'), $this->votePayload([$voter]))->assertForbidden();

        $member->forceFill(['can_create_votes' => true])->save();
        $this->createVote($member->fresh(), [$voter]);

        $this->assertSame(2, Vote::query()->count());
    }

    public function test_only_management_can_switch_on_vote_creation_for_others(): void
    {
        $staff = User::factory()->viewer()->create();
        $target = User::factory()->viewer()->create();

        $this->actingAs($staff)->putJson(route('admin.api.votes.creators.update', $target), ['can_create_votes' => true])->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('admin.api.votes.creators.update', $target), ['can_create_votes' => true])
            ->assertOk()
            ->assertJsonPath('data.can_create_votes', true);
    }

    public function test_only_eligible_accounts_can_vote_and_only_once(): void
    {
        $admin = User::factory()->admin()->create();
        $eligible = User::factory()->viewer()->create();
        $outsider = User::factory()->viewer()->create();
        $vote = $this->createVote($admin, [$eligible]);
        $optionId = $vote->options()->first()->id;

        $this->actingAs($outsider)
            ->postJson(route('admin.api.votes.cast', $vote), ['option_id' => $optionId])
            ->assertJsonValidationErrors('option_id');

        $this->actingAs($eligible)
            ->postJson(route('admin.api.votes.cast', $vote), ['option_id' => $optionId])
            ->assertOk()
            ->assertJsonPath('data.has_voted', true);

        $this->actingAs($eligible)
            ->postJson(route('admin.api.votes.cast', $vote), ['option_id' => $optionId])
            ->assertJsonValidationErrors('option_id');

        $this->assertSame(1, VoteReceipt::query()->count());
    }

    public function test_ballots_do_not_record_who_voted_and_results_wait_until_close(): void
    {
        $admin = User::factory()->admin()->create();
        $voters = [User::factory()->viewer()->create(), User::factory()->viewer()->create()];
        $vote = $this->createVote($admin, $voters);
        $optionA = $vote->options()->first();

        $this->assertFalse(Schema::hasColumn('vote_ballots', 'user_id'));

        $this->actingAs($voters[0])->postJson(route('admin.api.votes.cast', $vote), ['option_id' => $optionA->id])->assertOk();

        $this->actingAs($admin)->getJson(route('admin.api.votes.index'))
            ->assertJsonPath('data.0.results.closed', false)
            ->assertJsonPath('data.0.results.turnout', 1);

        $this->assertSame(1, VoteBallot::query()->where('vote_option_id', $optionA->id)->count());

        Carbon::setTestNow('2026-11-22T19:00:00');

        $this->actingAs($admin)->getJson(route('admin.api.votes.index'))
            ->assertJsonPath('data.0.results.closed', true)
            ->assertJsonPath('data.0.results.total', 1);
    }

    public function test_a_vote_cannot_be_cast_after_it_closes(): void
    {
        $admin = User::factory()->admin()->create();
        $voter = User::factory()->viewer()->create();
        $vote = $this->createVote($admin, [$voter]);

        Carbon::setTestNow('2026-11-22T19:00:00');

        $this->actingAs($voter)
            ->postJson(route('admin.api.votes.cast', $vote), ['option_id' => $vote->options()->first()->id])
            ->assertJsonValidationErrors('option_id');
    }

    public function test_only_the_creator_or_management_can_close_or_cancel(): void
    {
        $creator = User::factory()->admin()->create();
        $otherStaff = User::factory()->viewer()->create();
        $vote = $this->createVote($creator, [$otherStaff]);

        $this->actingAs(User::factory()->create(['account_type' => 'staff', 'role' => 'committee', 'can_create_votes' => true]))
            ->postJson(route('admin.api.votes.close', $vote))
            ->assertForbidden();

        $this->actingAs($creator)->postJson(route('admin.api.votes.close', $vote))->assertOk();
        $this->assertNotNull($vote->fresh()->closed_at);
    }

    public function test_details_can_be_changed_only_before_anyone_votes(): void
    {
        $admin = User::factory()->admin()->create();
        $voter = User::factory()->viewer()->create();
        $vote = $this->createVote($admin, [$voter]);

        $this->actingAs($admin)
            ->putJson(route('admin.api.votes.update', $vote), $this->votePayload([$voter], ['title' => 'Tajuk Baharu']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Tajuk Baharu');

        $this->actingAs($voter)->postJson(route('admin.api.votes.cast', $vote), ['option_id' => $vote->options()->first()->id])->assertOk();

        $this->actingAs($admin)
            ->putJson(route('admin.api.votes.update', $vote), $this->votePayload([$voter], ['title' => 'Lewat']))
            ->assertJsonValidationErrors('vote');
    }

    public function test_every_account_lists_every_vote_but_progress_is_for_management_and_the_creator(): void
    {
        $admin = User::factory()->admin()->create();
        $voter = User::factory()->viewer()->create();
        $bystander = User::factory()->viewer()->create();
        $vote = $this->createVote($admin, [$voter]);

        $this->actingAs($bystander)->getJson(route('admin.api.votes.index'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.results', null)
            ->assertJsonPath('data.0.progress_visible', false);

        $this->actingAs($voter)->getJson(route('admin.api.votes.index'))
            ->assertJsonPath('data.0.results', null)
            ->assertJsonPath('data.0.can_vote', true);

        $this->actingAs($admin)->getJson(route('admin.api.votes.index'))
            ->assertJsonPath('data.0.progress_visible', true)
            ->assertJsonPath('data.0.results.eligible', 1);

        $creator = User::factory()->viewer()->create(['can_create_votes' => true]);
        $ownVote = $this->createVote($creator, [$bystander]);
        $mine = collect($this->actingAs($creator)->getJson(route('admin.api.votes.index'))->json('data'))
            ->firstWhere('id', $ownVote->id);
        $this->assertTrue($mine['progress_visible']);
        $this->assertNotNull($mine['results']);
    }

    public function test_the_trail_records_voting_without_recording_the_choice(): void
    {
        $admin = User::factory()->admin()->create();
        $voter = User::factory()->viewer()->create(['name' => 'Pengundi Rahsia']);
        $vote = $this->createVote($admin, [$voter]);
        $option = $vote->options()->first();

        $this->actingAs($voter)->postJson(route('admin.api.votes.cast', $vote), ['option_id' => $option->id])->assertOk();

        $cast = ActivityLog::query()->where('event', 'vote.cast')->firstOrFail();
        $this->assertSame($voter->id, $cast->user_id);
        $this->assertSame(Vote::class, $cast->subject_type);
        $this->assertNull($cast->properties);
        $this->assertStringNotContainsString($option->label, (string) $cast->getRawOriginal('properties'));

        $this->actingAs($admin)->postJson(route('admin.api.votes.close', $vote))->assertOk();
        $this->assertDatabaseHas('activity_logs', ['event' => 'vote.updated', 'subject_id' => $vote->id]);
    }

    public function test_the_superadmin_can_include_themselves_as_a_voter_and_vote(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $vote = $this->createVote($superadmin, [$superadmin]);

        $this->actingAs($superadmin)
            ->postJson(route('admin.api.votes.cast', $vote), ['option_id' => $vote->options()->first()->id])
            ->assertOk()
            ->assertJsonPath('data.has_voted', true);
    }

    public function test_results_are_public_to_everyone_once_the_vote_closes(): void
    {
        $admin = User::factory()->admin()->create();
        $voter = User::factory()->viewer()->create();
        $bystander = User::factory()->viewer()->create();
        $vote = $this->createVote($admin, [$voter]);

        $this->actingAs($voter)->postJson(route('admin.api.votes.cast', $vote), ['option_id' => $vote->options()->first()->id])->assertOk();

        $this->actingAs($bystander)->getJson(route('admin.api.votes.index'))
            ->assertJsonPath('data.0.results', null);

        Carbon::setTestNow('2026-11-22T19:00:00');

        $this->actingAs($bystander)->getJson(route('admin.api.votes.index'))
            ->assertJsonPath('data.0.results.closed', true)
            ->assertJsonPath('data.0.results.total', 1)
            ->assertJsonPath('data.0.results.options.0.votes', 1);
    }

    public function test_choosing_management_positions_adds_everyone_holding_them(): void
    {
        $admin = User::factory()->admin()->create();
        $position = AccountPosition::factory()->create(['level' => 1]);
        $holder = User::factory()->viewer()->create(['account_position_id' => $position->id]);
        $other = User::factory()->viewer()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.api.votes.store'), array_merge(
            $this->votePayload([]),
            ['voter_ids' => [], 'account_position_ids' => [$position->id]],
        ))->assertCreated();

        $vote = Vote::query()->findOrFail($response->json('data.id'));
        $this->assertTrue($vote->voters()->whereKey($holder->id)->exists());
        $this->assertFalse($vote->voters()->whereKey($other->id)->exists());
        $this->assertSame([$position->id], $vote->accountPositions()->pluck('account_positions.id')->all());
    }

    public function test_a_vote_needs_at_least_one_voter_or_position(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson(route('admin.api.votes.store'), array_merge(
            $this->votePayload([]),
            ['voter_ids' => [], 'account_position_ids' => []],
        ))->assertJsonValidationErrors('voter_ids');
    }
}
