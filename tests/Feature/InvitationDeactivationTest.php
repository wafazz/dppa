<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationDeactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivating_an_account_revokes_its_open_invitations(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $admin = User::factory()->admin()->create();
        $ownLink = Invitation::factory()->create(['created_by_user_id' => $admin->id]);
        $otherLink = Invitation::factory()->create();

        app(AccountService::class)->update($superadmin, $admin, [
            'name' => $admin->name,
            'email' => $admin->email,
            'is_active' => false,
        ]);

        $this->assertNotNull($ownLink->fresh()->revoked_at);
        $this->assertNull($otherLink->fresh()->revoked_at);
    }

    public function test_invitations_from_an_inactive_creator_are_not_usable_even_without_revocation(): void
    {
        $creator = User::factory()->admin()->inactive()->create();
        $link = Invitation::factory()->create(['created_by_user_id' => $creator->id]);

        $this->assertFalse($link->isUsable());
        $this->assertSame(0, Invitation::query()->usable()->count());
    }

    public function test_reactivated_account_does_not_bring_back_revoked_links(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $admin = User::factory()->admin()->create();
        $link = Invitation::factory()->create(['created_by_user_id' => $admin->id]);

        app(AccountService::class)->update($superadmin, $admin, ['name' => $admin->name, 'email' => $admin->email, 'is_active' => false]);
        app(AccountService::class)->update($superadmin, $admin->fresh(), ['name' => $admin->name, 'email' => $admin->email, 'is_active' => true]);

        $this->assertNotNull($link->fresh()->revoked_at);
        $this->assertFalse($link->fresh()->isUsable());
    }
}
