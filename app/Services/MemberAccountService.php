<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\Member;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Every approved member has a login account. The member chooses the password through a set-up link,
 * which is e-mailed when Resend is connected and otherwise shown once to an administrator to pass on.
 */
class MemberAccountService
{
    public function __construct(
        private AccountSetupLinkService $setupLinks,
    ) {}

    /**
     * The member's login account, created if it does not exist yet.
     *
     * @throws ValidationException When another account already uses the member's e-mail.
     */
    public function ensureAccount(Member $member): User
    {
        $existing = User::query()->where('member_id', $member->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $email = strtolower(trim((string) $member->email));

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'account' => __('app.members.account.email_in_use'),
            ]);
        }

        return User::query()->forceCreate([
            'name' => $member->applicant_name,
            'email' => $email,
            'password' => null,
            'role' => null,
            'account_type' => AccountType::Member,
            'is_active' => true,
            'member_id' => $member->id,
        ]);
    }

    /**
     * Sends a set-up link by e-mail, or returns it once when e-mail is not connected.
     *
     * @return array{emailed: bool, url: ?string}
     */
    public function sendSetupLink(Member $member, ?User $actor): array
    {
        return $this->setupLinks->send($this->ensureAccount($member), $actor);
    }

    public function broker(): PasswordBroker
    {
        return Password::broker();
    }
}
