<?php

namespace Tests\Feature;

use App\Models\BusinessCategory;
use App\Models\ChamberPosition;
use App\Models\Member;
use App\Models\PositionAppointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_read_members_and_positions_but_not_change_them(): void
    {
        $viewer = User::factory()->viewer()->create();
        $member = Member::factory()->create();
        $position = ChamberPosition::factory()->create();

        $this->actingAs($viewer)->getJson(route('admin.api.members.index'))->assertForbidden();
        $this->actingAs($viewer)->getJson(route('admin.api.members.show', $member))->assertForbidden();
        $this->actingAs($viewer)->getJson(route('admin.api.positions.index'))->assertOk();

        $this->actingAs($viewer)
            ->putJson(route('admin.api.members.update', $member), [])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->postJson(route('admin.api.members.review', $member), ['status' => 'approved'])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->postJson(route('admin.api.appointments.store'), [])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->putJson(route('admin.api.positions.update', $position), [])
            ->assertForbidden();

        $this->assertSame('pending', $member->fresh()->status->value);
    }

    public function test_viewer_cannot_export_member_records(): void
    {
        $this->actingAs(User::factory()->viewer()->create())
            ->get(route('admin.api.members.export'))
            ->assertForbidden();
    }

    public function test_committee_can_review_and_assign_but_cannot_manage_position_names_or_categories(): void
    {
        $committee = User::factory()->create();
        $member = Member::factory()->create();

        $this->actingAs($committee)
            ->postJson(route('admin.api.members.review', $member), ['status' => 'approved'])
            ->assertOk();

        $this->actingAs($committee)
            ->postJson(route('admin.api.positions.store'), [])
            ->assertForbidden();

        $this->actingAs($committee)
            ->postJson(route('admin.api.categories.store'), [])
            ->assertForbidden();
    }

    public function test_admin_can_manage_position_names_and_categories(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.categories.store'), [
                'name_bm' => 'Perkhidmatan Kewangan Baharu',
                'name_en' => 'New Financial Services',
                'is_active' => true,
                'sort_order' => 50,
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson(route('admin.api.positions.store'), [
                'name_bm' => 'Penolong Setiausaha',
                'is_active' => true,
                'sort_order' => 8,
            ])
            ->assertCreated();
    }

    public function test_category_can_be_deactivated_but_not_removed(): void
    {
        $category = BusinessCategory::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('admin.api.categories.update', $category), [
                'name_bm' => $category->name_bm,
                'is_active' => false,
                'sort_order' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('business_categories', ['id' => $category->id]);
    }

    public function test_guests_cannot_reach_member_data_through_the_api(): void
    {
        $member = Member::factory()->create();

        $this->getJson(route('admin.api.members.index'))->assertUnauthorized();
        $this->getJson(route('admin.api.members.show', $member))->assertUnauthorized();
        $this->get(route('admin.api.members.export'))->assertRedirect(route('login'));
        $this->postJson(route('admin.api.members.review', $member), ['status' => 'approved'])->assertUnauthorized();
    }

    public function test_public_registration_page_does_not_expose_member_records(): void
    {
        Member::factory()->create(['applicant_name' => 'Rahsia Pemohon']);

        $this->get(route('registration.create'))->assertOk()->assertDontSee('Rahsia Pemohon');
    }

    public function test_appointment_index_requires_login(): void
    {
        PositionAppointment::factory()->create();

        $this->getJson(route('admin.api.appointments.index'))->assertUnauthorized();
    }
}
