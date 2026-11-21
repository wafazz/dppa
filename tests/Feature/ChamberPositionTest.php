<?php

namespace Tests\Feature;

use App\Models\ChamberPosition;
use App\Models\Member;
use App\Models\PositionAppointment;
use App\Models\User;
use Database\Seeders\ChamberPositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChamberPositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_six_initial_positions_once(): void
    {
        $this->seed(ChamberPositionSeeder::class);
        $this->seed(ChamberPositionSeeder::class);

        $this->assertSame(6, ChamberPosition::query()->count());
        $this->assertSame(
            ['Presiden', 'Timbalan Presiden', 'Setiausaha', 'Bendahari', 'Ahli Jawatankuasa', 'Ahli Biasa'],
            ChamberPosition::query()->orderBy('sort_order')->pluck('name_bm')->all(),
        );
    }

    public function test_admin_can_add_and_rename_a_position(): void
    {
        $admin = User::factory()->admin()->create();

        $created = $this->actingAs($admin)
            ->postJson(route('admin.api.positions.store'), [
                'name_bm' => 'Penasihat',
                'name_en' => 'Adviser',
                'is_active' => true,
                'sort_order' => 7,
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($admin)
            ->putJson(route('admin.api.positions.update', $created['id']), [
                'name_bm' => 'Penasihat Kanan',
                'name_en' => 'Senior Adviser',
                'is_active' => true,
                'sort_order' => 7,
            ])
            ->assertOk()
            ->assertJsonPath('data.name_bm', 'Penasihat Kanan');
    }

    public function test_duplicate_position_name_is_rejected(): void
    {
        $this->seed(ChamberPositionSeeder::class);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.positions.store'), [
                'name_bm' => 'Presiden',
                'is_active' => true,
                'sort_order' => 9,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name_bm');
    }

    public function test_committee_member_cannot_change_position_names(): void
    {
        $position = ChamberPosition::factory()->create();

        $this->actingAs(User::factory()->create())
            ->putJson(route('admin.api.positions.update', $position), [
                'name_bm' => 'Diubah',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertForbidden();

        $this->assertNotSame('Diubah', $position->fresh()->name_bm);
    }

    public function test_approved_member_can_be_appointed_with_term_dates(): void
    {
        $committee = User::factory()->create();
        $member = Member::factory()->approved()->create();
        $position = ChamberPosition::factory()->create();

        $this->actingAs($committee)
            ->postJson(route('admin.api.appointments.store'), [
                'member_id' => $member->id,
                'chamber_position_id' => $position->id,
                'term_start' => '2026-01-01',
                'term_end' => '2028-12-31',
                'is_current' => true,
                'notes' => '<p>Dipilih pada mesyuarat agung</p>',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_current', true)
            ->assertJsonPath('data.chamber_position.id', $position->id);

        $appointment = PositionAppointment::query()->sole();

        $this->assertSame($committee->id, $appointment->created_by_user_id);
        $this->assertSame('2026-01-01', $appointment->term_start->toDateString());
    }

    public function test_appointment_notes_are_sanitized_before_storage(): void
    {
        $member = Member::factory()->approved()->create();
        $position = ChamberPosition::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.api.appointments.store'), [
                'member_id' => $member->id,
                'chamber_position_id' => $position->id,
                'term_start' => '2026-01-01',
                'is_current' => false,
                'notes' => '<p onmouseover="steal()">Catatan <script>alert(1)</script><strong>penting</strong></p>',
            ])
            ->assertCreated();

        $notes = PositionAppointment::query()->sole()->notes;

        $this->assertStringNotContainsString('<script', $notes);
        $this->assertStringNotContainsString('onmouseover', $notes);
        $this->assertStringContainsString('<strong>penting</strong>', $notes);
    }

    public function test_pending_member_cannot_be_appointed(): void
    {
        $member = Member::factory()->create();
        $position = ChamberPosition::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.api.appointments.store'), [
                'member_id' => $member->id,
                'chamber_position_id' => $position->id,
                'term_start' => '2026-01-01',
                'is_current' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member_id');
    }

    public function test_inactive_position_cannot_be_assigned(): void
    {
        $member = Member::factory()->approved()->create();
        $position = ChamberPosition::factory()->create(['is_active' => false]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.api.appointments.store'), [
                'member_id' => $member->id,
                'chamber_position_id' => $position->id,
                'term_start' => '2026-01-01',
                'is_current' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('chamber_position_id');
    }

    public function test_term_end_cannot_be_before_term_start(): void
    {
        $member = Member::factory()->approved()->create();
        $position = ChamberPosition::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.api.appointments.store'), [
                'member_id' => $member->id,
                'chamber_position_id' => $position->id,
                'term_start' => '2026-06-01',
                'term_end' => '2026-01-01',
                'is_current' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('term_end');
    }

    public function test_current_appointment_with_a_finished_term_is_rejected(): void
    {
        $member = Member::factory()->approved()->create();
        $position = ChamberPosition::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.api.appointments.store'), [
                'member_id' => $member->id,
                'chamber_position_id' => $position->id,
                'term_start' => '2020-01-01',
                'term_end' => '2021-01-01',
                'is_current' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('term_end');
    }

    public function test_ending_a_term_keeps_it_in_position_history(): void
    {
        $committee = User::factory()->create();
        $appointment = PositionAppointment::factory()->current()->create();

        $this->actingAs($committee)
            ->putJson(route('admin.api.appointments.update', $appointment), [
                'member_id' => $appointment->member_id,
                'chamber_position_id' => $appointment->chamber_position_id,
                'term_start' => $appointment->term_start->toDateString(),
                'term_end' => now()->subDay()->toDateString(),
                'is_current' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_current', false);

        $this->assertDatabaseHas('position_appointments', ['id' => $appointment->id, 'is_current' => false]);
        $this->assertDatabaseCount('position_appointments', 1);
    }

    public function test_appointment_history_can_be_filtered_to_current_terms(): void
    {
        $committee = User::factory()->create();
        $current = PositionAppointment::factory()->current()->create();
        PositionAppointment::factory()->create(['is_current' => false, 'term_end' => '2020-12-31']);

        $this->actingAs($committee)
            ->getJson(route('admin.api.appointments.index', ['current' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $current->id);

        $this->actingAs($committee)
            ->getJson(route('admin.api.appointments.index'))
            ->assertJsonCount(2, 'data');
    }

    public function test_existing_appointment_can_be_ended_after_its_position_is_deactivated(): void
    {
        $position = ChamberPosition::factory()->create(['is_active' => false]);
        $appointment = PositionAppointment::factory()->current()->create(['chamber_position_id' => $position->id]);

        $this->actingAs(User::factory()->create())
            ->putJson(route('admin.api.appointments.update', $appointment), [
                'member_id' => $appointment->member_id,
                'chamber_position_id' => $position->id,
                'term_start' => $appointment->term_start->toDateString(),
                'term_end' => now()->subDay()->toDateString(),
                'is_current' => false,
            ])
            ->assertOk();
    }

    public function test_appointment_cannot_be_moved_to_a_deactivated_position(): void
    {
        $appointment = PositionAppointment::factory()->current()->create();
        $inactive = ChamberPosition::factory()->create(['is_active' => false]);

        $this->actingAs(User::factory()->create())
            ->putJson(route('admin.api.appointments.update', $appointment), [
                'member_id' => $appointment->member_id,
                'chamber_position_id' => $inactive->id,
                'term_start' => $appointment->term_start->toDateString(),
                'is_current' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('chamber_position_id');
    }

    public function test_leaving_approved_status_ends_current_appointments_without_deleting_them(): void
    {
        $committee = User::factory()->create();
        $appointment = PositionAppointment::factory()->current()->create();
        $member = $appointment->member;

        $this->actingAs($committee)
            ->postJson(route('admin.api.members.review', $member), ['status' => 'rejected', 'note' => '<p>Tidak lengkap</p>'])
            ->assertOk();

        $appointment->refresh();

        $this->assertFalse($appointment->is_current);
        $this->assertNotNull($appointment->term_end);
        $this->assertDatabaseCount('position_appointments', 1);
    }
}
