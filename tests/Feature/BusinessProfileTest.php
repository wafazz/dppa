<?php

namespace Tests\Feature;

use App\Models\BusinessCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An account whose business details were saved, with an SSM document, and submitted.
     */
    private function submittedUser(array $overrides = []): User
    {
        return User::factory()->viewer()->create(array_merge([
            'business_name' => 'Kedai Faiz Enterprise',
            'ssm_no' => '202401012345',
            'business_document_path' => 'business-documents/user-1.pdf',
            'business_document_name' => 'ssm.pdf',
            'business_submitted_at' => now(),
        ], $overrides));
    }

    public function test_draft_can_be_saved_without_the_document(): void
    {
        $category = BusinessCategory::factory()->create();
        $user = User::factory()->viewer()->create();

        $this->actingAs($user)
            ->putJson(route('admin.api.profile.business'), [
                'business_name' => 'Kedai Faiz Enterprise',
                'business_category_id' => $category->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.business_status', 'draft')
            ->assertJsonPath('data.has_business_document', false);
    }

    public function test_submitting_needs_name_ssm_number_and_the_document(): void
    {
        Storage::fake('local');
        $user = User::factory()->viewer()->create();

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.business.submit'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['business_name', 'ssm_no', 'ssm']);

        $this->assertNull($user->fresh()->business_submitted_at);
    }

    public function test_ssm_upload_accepts_pdf_and_rejects_other_files_and_large_ones(): void
    {
        Storage::fake('local');
        $user = User::factory()->viewer()->create();

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.business.document.store'), ['ssm' => UploadedFile::fake()->create('ssm.pdf', 200, 'application/pdf')])
            ->assertOk()
            ->assertJsonPath('data.has_business_document', true)
            ->assertJsonPath('data.business_document_name', 'ssm.pdf');

        Storage::disk('local')->assertExists('business-documents/user-'.$user->id.'.pdf');

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.business.document.store'), ['ssm' => UploadedFile::fake()->create('nota.txt', 10, 'text/plain')])
            ->assertJsonValidationErrors('ssm');

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.business.document.store'), ['ssm' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')])
            ->assertJsonValidationErrors('ssm');
    }

    public function test_complete_draft_can_be_submitted_and_any_change_returns_it_to_draft(): void
    {
        Storage::fake('local');
        $user = User::factory()->viewer()->create();

        $this->actingAs($user)
            ->putJson(route('admin.api.profile.business'), ['business_name' => 'Kedai Faiz', 'ssm_no' => '202401012345']);
        $this->actingAs($user)
            ->postJson(route('admin.api.profile.business.document.store'), ['ssm' => UploadedFile::fake()->create('ssm.pdf', 100, 'application/pdf')]);

        $this->actingAs($user)
            ->postJson(route('admin.api.profile.business.submit'))
            ->assertOk()
            ->assertJsonPath('data.business_status', 'submitted');

        $this->actingAs($user)
            ->putJson(route('admin.api.profile.business'), ['business_name' => 'Kedai Faiz Baharu', 'ssm_no' => '202401012345'])
            ->assertJsonPath('data.business_status', 'draft');
    }

    public function test_draft_cannot_be_verified_until_it_is_submitted(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->viewer()->create(['business_name' => 'Kedai Faiz']);

        $this->actingAs($admin)
            ->postJson(route('admin.api.accounts.verify-business', $user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('business');

        $this->assertNull($user->fresh()->business_verified_at);
    }

    public function test_verified_business_details_are_locked_for_the_account_holder(): void
    {
        $admin = User::factory()->admin()->create();
        $user = $this->submittedUser();

        $this->actingAs($admin)
            ->postJson(route('admin.api.accounts.verify-business', $user))
            ->assertOk()
            ->assertJsonPath('data.business_status', 'verified');

        $this->actingAs($user->fresh())
            ->putJson(route('admin.api.profile.business'), ['business_name' => 'Nama Lain'])
            ->assertForbidden();

        $this->actingAs($user->fresh())
            ->postJson(route('admin.api.profile.business.document.store'), ['ssm' => UploadedFile::fake()->create('ssm.pdf', 100, 'application/pdf')])
            ->assertForbidden();

        $this->assertSame('Kedai Faiz Enterprise', $user->fresh()->business_name);
        $this->assertSame($admin->id, $user->fresh()->business_verified_by_user_id);
    }

    public function test_only_management_accounts_can_verify_and_never_their_own(): void
    {
        $user = $this->submittedUser();

        $this->actingAs(User::factory()->viewer()->create())
            ->postJson(route('admin.api.accounts.verify-business', $user))
            ->assertForbidden();

        $admin = User::factory()->admin()->create(['business_name' => 'Admin Biz', 'ssm_no' => '1', 'business_submitted_at' => now()]);
        $this->actingAs($admin)
            ->postJson(route('admin.api.accounts.verify-business', $admin))
            ->assertForbidden();

        $this->assertNull($user->fresh()->business_verified_at);
    }

    public function test_ssm_document_is_visible_to_its_owner_and_to_administrators_only(): void
    {
        Storage::fake('local');
        $owner = User::factory()->viewer()->create();

        $this->actingAs($owner)
            ->getJson(route('admin.api.profile.business.document.show'))
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson(route('admin.api.profile.business.document.store'), ['ssm' => UploadedFile::fake()->create('ssm.pdf', 100, 'application/pdf')]);

        $this->actingAs($owner)
            ->get(route('admin.api.profile.business.document.show'))
            ->assertOk();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.api.accounts.business-document', $owner))
            ->assertOk();

        $this->actingAs(User::factory()->viewer()->create())
            ->get(route('admin.api.accounts.business-document', $owner))
            ->assertForbidden();
    }

    public function test_accounts_list_shows_whether_business_can_be_verified(): void
    {
        $admin = User::factory()->admin()->create();
        $submitted = $this->submittedUser();
        User::factory()->viewer()->create(['business_name' => 'Draf Sahaja']);

        $accounts = collect($this->actingAs($admin)->getJson(route('admin.api.accounts.index'))->json('data'));

        $this->assertTrue($accounts->firstWhere('id', $submitted->id)['can_verify_business']);
        $this->assertSame('submitted', $accounts->firstWhere('id', $submitted->id)['business_status']);
        $this->assertFalse($accounts->firstWhere('business_name', 'Draf Sahaja')['can_verify_business']);
    }
}
