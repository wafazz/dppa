<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'account_type',
    'can_invite',
    'is_active',
    'account_position_id',
])]
#[Hidden(['password', 'remember_token', 'two_factor_authenticator_secret', 'two_factor_authenticator_last_step'])]
class User extends Authenticatable
{
    /**
     * @return array<int, string>
     */
    protected function activityExcluded(): array
    {
        return array_merge(['created_at', 'updated_at'], ['password', 'remember_token', 'two_factor_authenticator_secret', 'two_factor_authenticator_last_step']);
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    /**
     * @return BelongsTo<BusinessCategory, $this>
     */
    public function businessCategory(): BelongsTo
    {
        return $this->belongsTo(BusinessCategory::class, 'business_category_id');
    }

    /**
     * Business details are locked once an administrator has verified them.
     */
    public function hasVerifiedBusiness(): bool
    {
        return $this->business_verified_at !== null;
    }

    /**
     * draft: saved but not submitted (or changed since submission); submitted: waiting for an administrator; verified: locked.
     */
    public function businessStatus(): string
    {
        if ($this->hasVerifiedBusiness()) {
            return 'verified';
        }

        return $this->business_submitted_at !== null ? 'submitted' : 'draft';
    }

    /**
     * @return BelongsTo<AccountPosition, $this>
     */
    public function accountPosition(): BelongsTo
    {
        return $this->belongsTo(AccountPosition::class, 'account_position_id');
    }

    public function isSuperadmin(): bool
    {
        return $this->account_type === AccountType::Superadmin;
    }

    /**
     * Superadmin and admin accounts manage the system.
     */
    public function isManagement(): bool
    {
        return $this->account_type->isManagement();
    }

    public function isStaff(): bool
    {
        return $this->account_type === AccountType::Staff;
    }

    public function isMember(): bool
    {
        return $this->account_type === AccountType::Member;
    }

    /**
     * Management accounts and any account the administrators have allowed to create votes.
     */
    public function canCreateVotes(): bool
    {
        return $this->isManagement() || ($this->can_create_votes && ! $this->isSuperadmin());
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Management accounts and committee staff may create and change member records and appointments.
     */
    public function canManageMembers(): bool
    {
        if ($this->isMember()) {
            return false;
        }

        return $this->isManagement() || $this->role === UserRole::Committee;
    }

    /**
     * Management accounts and staff marked by an administrator may create invitations.
     */
    public function canInvite(): bool
    {
        return $this->isManagement() || $this->can_invite;
    }

    /**
     * Management rank: the level of the account position, or null when the account has none.
     */
    public function managementLevel(): ?int
    {
        return $this->accountPosition?->level;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'account_type' => AccountType::class,
            'can_invite' => 'boolean',
            'is_active' => 'boolean',
            'can_create_votes' => 'boolean',
            'business_verified_at' => 'datetime',
            'business_submitted_at' => 'datetime',
            'two_factor_authenticator_secret' => 'encrypted',
            'two_factor_authenticator_confirmed_at' => 'datetime',
            'two_factor_authenticator_last_step' => 'integer',
            'two_factor_email_enabled' => 'boolean',
            'two_factor_whatsapp_enabled' => 'boolean',
        ];
    }
}
