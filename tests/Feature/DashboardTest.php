<?php

namespace Tests\Feature;

use App\Models\BusinessCategory;
use App\Models\ChamberPosition;
use App\Models\Member;
use App\Models\PositionAppointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_the_landing_page_for_committee_accounts(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-vue="dashboard-index"', escape: false);
    }

    public function test_guests_are_sent_to_login_before_the_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->getJson(route('admin.api.dashboard.show'))->assertUnauthorized();
    }

    public function test_committee_receives_counts_series_and_lists(): void
    {
        Member::factory()->count(2)->create();
        Member::factory()->approved()->create(['applicant_name' => 'Ahli Diluluskan']);
        Member::factory()->rejected()->create();

        $category = BusinessCategory::factory()->create(['name_bm' => 'Teknologi Dashboard']);
        Member::factory()->count(2)->create(['business_category_id' => $category->id]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.api.dashboard.show', ['months' => 12]))
            ->assertOk()
            ->assertJsonPath('data.can_view_members', true)
            ->assertJsonPath('data.kpis.total', 6)
            ->assertJsonPath('data.kpis.pending', 4)
            ->assertJsonPath('data.kpis.approved', 1)
            ->assertJsonPath('data.kpis.rejected', 1)
            ->assertJsonCount(12, 'data.monthly.labels')
            ->assertJsonCount(12, 'data.monthly.applications')
            ->assertJsonPath('data.categories.0.name', 'Teknologi Dashboard')
            ->assertJsonCount(5, 'data.recent_applications');
    }

    public function test_monthly_approvals_are_counted_in_the_month_they_happened(): void
    {
        Member::factory()->approved()->create(['approved_at' => now()->startOfMonth()->addDay()]);

        $series = $this->actingAs(User::factory()->create())
            ->getJson(route('admin.api.dashboard.show'))
            ->assertOk()
            ->json('data.monthly');

        $this->assertSame(1, end($series['approvals']));
        $this->assertSame(1, end($series['applications']));
    }

    public function test_viewers_get_positions_only_and_no_applicant_figures(): void
    {
        Member::factory()->create(['applicant_name' => 'Rahsia Pemohon']);
        $appointment = PositionAppointment::factory()->current()->create();

        $response = $this->actingAs(User::factory()->viewer()->create())
            ->getJson(route('admin.api.dashboard.show'))
            ->assertOk()
            ->assertJsonPath('data.can_view_members', false)
            ->assertJsonPath('data.positions_current', 1)
            ->assertJsonMissingPath('data.kpis')
            ->assertJsonMissingPath('data.recent_applications');

        $this->assertStringNotContainsString('Rahsia Pemohon', $response->getContent());
        $this->assertNotNull($appointment->id);
    }

    public function test_expiring_terms_list_only_current_terms_ending_within_sixty_days(): void
    {
        $position = ChamberPosition::factory()->create(['name_bm' => 'Setiausaha Uji']);
        PositionAppointment::factory()->create([
            'chamber_position_id' => $position->id,
            'is_current' => true,
            'term_start' => now()->subYear()->toDateString(),
            'term_end' => now()->addDays(20)->toDateString(),
        ]);
        PositionAppointment::factory()->create([
            'is_current' => true,
            'term_start' => now()->subYear()->toDateString(),
            'term_end' => now()->addYear()->toDateString(),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.api.dashboard.show'))
            ->assertOk()
            ->assertJsonCount(1, 'data.expiring_terms')
            ->assertJsonPath('data.expiring_terms.0.position', 'Setiausaha Uji');
    }

    public function test_months_parameter_is_limited_to_six_or_twelve(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.api.dashboard.show', ['months' => 3]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('months');
    }
}
