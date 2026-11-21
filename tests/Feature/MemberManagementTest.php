<?php

namespace Tests\Feature;

use App\Models\BusinessCategory;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_by_name_email_and_ssm_number(): void
    {
        $committee = User::factory()->create();
        $target = Member::factory()->create(['applicant_name' => 'Razak Hussain', 'ssm_no' => '999999999999-A']);
        Member::factory()->count(3)->create();

        $this->actingAs($committee)
            ->getJson(route('admin.api.members.index', ['q' => 'Razak']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id);

        $this->actingAs($committee)
            ->getJson(route('admin.api.members.index', ['q' => $target->email]))
            ->assertJsonCount(1, 'data');

        $this->actingAs($committee)
            ->getJson(route('admin.api.members.index', ['q' => '999999999999-A']))
            ->assertJsonCount(1, 'data');
    }

    public function test_list_filters_by_status_and_category(): void
    {
        $committee = User::factory()->create();
        $category = BusinessCategory::factory()->create();
        $matching = Member::factory()->approved()->create(['business_category_id' => $category->id]);
        Member::factory()->create(['business_category_id' => $category->id]);
        Member::factory()->approved()->create();

        $this->actingAs($committee)
            ->getJson(route('admin.api.members.index', [
                'status' => 'approved',
                'category_id' => $category->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);
    }

    public function test_services_filter_searches_product_and_service_descriptions(): void
    {
        $committee = User::factory()->create();
        $bakery = Member::factory()->create(['search_text' => 'Roti sourdough dan kek tempahan']);
        Member::factory()->create(['search_text' => 'Pembaikan kenderaan']);

        $this->actingAs($committee)
            ->getJson(route('admin.api.members.index', ['services' => 'kek tempahan']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bakery->id);
    }

    public function test_search_treats_like_wildcards_as_plain_text(): void
    {
        $committee = User::factory()->create();
        Member::factory()->count(2)->create();

        $this->actingAs($committee)
            ->getJson(route('admin.api.members.index', ['q' => '%']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_list_sorts_and_paginates(): void
    {
        $committee = User::factory()->create();
        Member::factory()->create(['applicant_name' => 'Zainal']);
        Member::factory()->create(['applicant_name' => 'Aminah']);
        Member::factory()->create(['applicant_name' => 'Mahmud']);
        Member::factory()->create(['applicant_name' => 'Zulkifli']);

        Member::factory()->create(['applicant_name' => 'Zahra']);

        Member::factory()->create(['applicant_name' => 'Zaki']);

        $this->actingAs($committee)
            ->getJson(route('admin.api.members.index', [
                'sort' => 'applicant_name',
                'direction' => 'asc',
                'per_page' => 5,
            ]))
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.applicant_name', 'Aminah')
            ->assertJsonPath('meta.total', 6)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_invalid_sort_column_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.api.members.index', ['sort' => 'password']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    public function test_member_detail_lists_possible_duplicate_records(): void
    {
        $committee = User::factory()->create();
        $member = Member::factory()->create(['email' => 'kongsi@example.com']);
        $duplicate = Member::factory()->create(['email' => 'kongsi@example.com']);

        $this->actingAs($committee)
            ->getJson(route('admin.api.members.show', $member))
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('possible_duplicates.0.id', $duplicate->id);
    }

    public function test_committee_can_update_member_details_with_sanitized_rich_text(): void
    {
        $committee = User::factory()->create();
        $category = BusinessCategory::factory()->create();
        $member = Member::factory()->create();

        $this->actingAs($committee)
            ->putJson(route('admin.api.members.update', $member), [
                'applicant_name' => 'Nora Ismail',
                'business_name' => 'Nora Catering',
                'business_category_id' => $category->id,
                'business_address' => 'No. 5, Jalan Puncak 2',
                'phone' => '013-2223344',
                'email' => 'nora@example.com',
                'ssm_no' => '',
                'business_description' => '<p onmouseover="x()">Katering <em>majlis</em></p>',
                'business_needs' => '<p>Pemasaran digital</p>',
            ])
            ->assertOk()
            ->assertJsonPath('data.applicant_name', 'Nora Ismail')
            ->assertJsonPath('data.ssm_no', null);

        $member->refresh();

        $this->assertSame('Nora Catering', $member->business_name);
        $this->assertNull($member->ssm_no);
        $this->assertStringNotContainsString('onmouseover', $member->business_description);
        $this->assertStringContainsString('Katering', $member->search_text);
    }

    public function test_updating_to_an_ssm_number_used_by_another_active_member_is_rejected(): void
    {
        $committee = User::factory()->create();
        Member::factory()->create(['ssm_no' => '111111111111-B']);
        $member = Member::factory()->create(['ssm_no' => null]);

        $this->actingAs($committee)
            ->putJson(route('admin.api.members.update', $member), $this->detailPayload(['ssm_no' => '111111111111-B']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ssm_no');
    }

    public function test_member_may_keep_their_own_ssm_number_when_updated(): void
    {
        $committee = User::factory()->create();
        $member = Member::factory()->create(['ssm_no' => '222222222222-C']);

        $this->actingAs($committee)
            ->putJson(route('admin.api.members.update', $member), $this->detailPayload(['ssm_no' => '222222222222-C']))
            ->assertOk();
    }

    public function test_option_list_for_assignments_only_includes_approved_members(): void
    {
        $committee = User::factory()->create();
        $approved = Member::factory()->approved()->create(['applicant_name' => 'Hafiz Rahman']);
        Member::factory()->create(['applicant_name' => 'Hafiz Pending']);

        $this->actingAs($committee)
            ->getJson(route('admin.api.members.options', ['q' => 'Hafiz']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function detailPayload(array $overrides = []): array
    {
        return array_merge([
            'applicant_name' => 'Pemohon Uji',
            'business_name' => 'Perniagaan Uji',
            'business_category_id' => BusinessCategory::factory()->create()->id,
            'business_address' => 'Alamat ujian',
            'phone' => '012-0000000',
            'email' => 'uji@example.com',
            'ssm_no' => null,
            'business_description' => '<p>Penerangan</p>',
            'business_needs' => '<p>Keperluan</p>',
        ], $overrides);
    }
}
