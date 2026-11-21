<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_is_the_default_language(): void
    {
        Member::factory()->approved()->create();

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.api.members.index'))
            ->assertOk()
            ->assertJsonPath('data.0.status_label', 'Approved');
    }

    public function test_switching_to_malay_translates_api_labels_and_pages(): void
    {
        Member::factory()->approved()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('locale.switch', 'ms'))->assertRedirect();

        $this->actingAs($user)
            ->getJson(route('admin.api.members.index'))
            ->assertOk()
            ->assertJsonPath('data.0.status_label', 'Diluluskan');

        $this->actingAs($user)
            ->get(route('admin.members.index'))
            ->assertOk()
            ->assertSee('lang="ms"', escape: false)
            ->assertSee('Pangkalan Data Ahli');
    }

    public function test_switching_back_to_english_restores_english_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('locale.switch', 'ms'));
        $this->actingAs($user)->get(route('locale.switch', 'en'));

        $this->actingAs($user)
            ->get(route('admin.members.index'))
            ->assertSee('lang="en"', escape: false)
            ->assertSee('Member Database');
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $this->get('/locale/fr')->assertNotFound();
    }
}
