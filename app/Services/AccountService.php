<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\UserRole;
use App\Models\AccountPosition;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and changes system accounts under the hierarchy rules:
 * - the superadmin manages every admin and staff account;
 * - an admin manages only staff accounts whose position ranks below the admin's own position;
 * - nobody manages their own account here, and no superadmin account is managed through the interface.
 */
class AccountService
{
    public function __construct(private EmailNotices $notices) {}

    /**
     * Whether the actor may change the target account.
     */
    public function canManage(User $actor, User $target): bool
    {
        // Member accounts are managed from the member record, never from the system accounts list.
        if ($actor->is($target) || $target->isSuperadmin() || $target->isMember()) {
            return false;
        }

        if ($actor->isSuperadmin()) {
            return true;
        }

        if ($actor->account_type !== AccountType::Admin || ! $target->isStaff()) {
            return false;
        }

        $actorLevel = $actor->managementLevel();

        if ($actorLevel === null) {
            return false;
        }

        // A staff account without a position is treated as the lowest rank.
        return ($target->managementLevel() ?? PHP_INT_MAX) > $actorLevel;
    }

    /**
     * Whether the actor may give a staff account this position.
     */
    public function canAssignPosition(User $actor, ?AccountPosition $position): bool
    {
        if ($position === null || $actor->isSuperadmin()) {
            return true;
        }

        $actorLevel = $actor->managementLevel();

        return $actorLevel !== null && $position->level > $actorLevel;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function create(User $actor, array $data): User
    {
        $type = AccountType::from((string) $data['account_type']);

        if ($type === AccountType::Admin && ! $actor->isSuperadmin()) {
            throw new AuthorizationException(__('app.validation.admin_only'));
        }

        $staffAttributes = $this->staffAttributes($actor, $type, $data);

        $attributes = [
            'name' => trim((string) $data['name']),
            'email' => strtolower(trim((string) $data['email'])),
            'account_type' => $type,
            'is_active' => true,
        ] + $staffAttributes;

        // Without a password the account holder chooses one through the set-up link.
        if (! empty($data['password'])) {
            $attributes['password'] = (string) $data['password'];
        }

        return DB::transaction(fn (): User => User::query()->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function update(User $actor, User $target, array $data): User
    {
        if (! $this->canManage($actor, $target)) {
            throw new AuthorizationException(__('app.validation.account_forbidden'));
        }

        $attributes = [
            'name' => trim((string) $data['name']),
            'email' => strtolower(trim((string) $data['email'])),
            'is_active' => (bool) $data['is_active'],
        ];

        if (! empty($data['password'])) {
            $attributes['password'] = (string) $data['password'];
        }

        if ($target->isStaff()) {
            $attributes += $this->staffAttributes($actor, AccountType::Staff, $data);
        }

        $wasActive = $target->is_active;

        $target->update($attributes);

        if ($wasActive && ! $target->is_active) {
            $this->revokeInvitationsOf($target);
        }

        if ($wasActive !== $target->is_active) {
            $this->notices->deliver(
                $target->is_active ? 'account_reactivated' : 'account_deactivated',
                (string) $target->email,
                ['name' => $target->name],
                $target->is_active ? route('login') : null,
            );
        }

        return $target->refresh();
    }

    /**
     * A deactivated account's open invitation links stop working straight away.
     */
    private function revokeInvitationsOf(User $account): void
    {
        Invitation::query()
            ->where('created_by_user_id', $account->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now()]);
    }

    /**
     * Role, invitation permission and position apply only to staff accounts.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function staffAttributes(User $actor, AccountType $type, array $data): array
    {
        if ($type !== AccountType::Staff) {
            return [
                'role' => UserRole::Committee,
                'can_invite' => false,
                'account_position_id' => null,
            ];
        }

        $position = isset($data['account_position_id'])
            ? AccountPosition::query()->find($data['account_position_id'])
            : null;

        if (! $this->canAssignPosition($actor, $position)) {
            throw ValidationException::withMessages([
                'account_position_id' => __('app.validation.position_not_below'),
            ]);
        }

        return [
            'role' => UserRole::from((string) ($data['role'] ?? UserRole::Committee->value)),
            'can_invite' => (bool) ($data['can_invite'] ?? false),
            'account_position_id' => $position?->id,
        ];
    }
}
