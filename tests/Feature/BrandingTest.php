<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_superadmin_can_manage_the_logo(): void
    {
        Storage::fake(Branding::DISK);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.branding.store'), ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertForbidden();

        $this->actingAs(User::factory()->viewer()->create())
            ->getJson(route('admin.api.branding.show'))
            ->assertForbidden();

        $this->actingAs(User::factory()->superadmin()->create())
            ->postJson(route('admin.api.branding.store'), ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertOk()
            ->assertJsonStructure(['data' => ['logo_url']]);
    }

    public function test_the_uploaded_logo_is_stored_and_shown_in_the_header(): void
    {
        Storage::fake(Branding::DISK);
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->postJson(route('admin.api.branding.store'), ['logo' => UploadedFile::fake()->image('logo.png', 300, 150)])
            ->assertOk();

        Storage::disk(Branding::DISK)->assertExists(Branding::LOGO_FILE);

        $this->actingAs($superadmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="brand-logo-img', escape: false)
            ->assertDontSee('brand-text', escape: false);
    }

    public function test_a_new_upload_replaces_the_previous_logo(): void
    {
        Storage::fake(Branding::DISK);
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->postJson(route('admin.api.branding.store'), ['logo' => UploadedFile::fake()->image('a.png', 100, 100)])->assertOk();
        $this->actingAs($superadmin)->postJson(route('admin.api.branding.store'), ['logo' => UploadedFile::fake()->image('b.png', 120, 120)])->assertOk();

        $this->assertCount(1, Storage::disk(Branding::DISK)->allFiles('branding'));
    }

    public function test_invalid_logos_are_rejected(): void
    {
        Storage::fake(Branding::DISK);
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->postJson(route('admin.api.branding.store'), ['logo' => UploadedFile::fake()->create('logo.pdf', 50, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('logo');

        $this->actingAs($superadmin)
            ->postJson(route('admin.api.branding.store'), ['logo' => UploadedFile::fake()->image('big.png', 600, 600)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('logo');

        $this->actingAs($superadmin)
            ->postJson(route('admin.api.branding.store'), ['logo' => UploadedFile::fake()->image('tiny.png', 16, 16)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('logo');

        Storage::disk(Branding::DISK)->assertMissing(Branding::LOGO_FILE);
    }

    public function test_removing_the_logo_restores_the_default_name(): void
    {
        Storage::fake(Branding::DISK);
        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin)->postJson(route('admin.api.branding.store'), ['logo' => UploadedFile::fake()->image('logo.png', 100, 100)])->assertOk();

        $this->actingAs($superadmin)
            ->deleteJson(route('admin.api.branding.destroy'))
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);

        Storage::disk(Branding::DISK)->assertMissing(Branding::LOGO_FILE);
    }
}
