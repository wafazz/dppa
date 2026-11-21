<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in user's own details, password, and business details. Email is not changed here:
 * it identifies the account. Business details lock once an administrator verifies them.
 */
class ProfileService
{
    public function __construct(
        private AccountService $accounts,
        private ActivityLogger $activity,
    ) {}

    /**
     * @param  array{name: string, address_line1: string, address_line2?: ?string, postcode?: ?string, city?: ?string, state?: ?string}  $data
     */
    public function updatePersonal(User $user, array $data): User
    {
        $user->forceFill([
            'name' => trim($data['name']),
            'address_line1' => $this->clean($data['address_line1']),
            'address_line2' => $this->clean($data['address_line2'] ?? null),
            'postcode' => $this->clean($data['postcode'] ?? null),
            'city' => $this->clean($data['city'] ?? null),
            'state' => $this->clean($data['state'] ?? null),
        ])->save();

        return $user->refresh();
    }

    /**
     * @param  array{business_name?: ?string, ssm_no?: ?string, business_category_id?: ?int, business_phone?: ?string}  $data
     *
     * @throws AuthorizationException When the business details are already verified.
     */
    public function updateBusiness(User $user, array $data): User
    {
        if ($user->hasVerifiedBusiness()) {
            throw new AuthorizationException(__('app.profile.business_locked_error'));
        }

        $user->forceFill([
            'business_name' => $this->clean($data['business_name'] ?? null),
            'ssm_no' => $this->clean($data['ssm_no'] ?? null),
            'business_category_id' => $data['business_category_id'] ?? null,
            'business_phone' => $this->clean($data['business_phone'] ?? null),
            'business_submitted_at' => null,
        ])->save();

        return $user->refresh();
    }

    /**
     * Uploading a new SSM certificate returns the business details to draft, so an administrator
     * always verifies the document that was submitted.
     *
     * @throws AuthorizationException When the business details are already verified.
     */
    public function storeBusinessDocument(User $user, BusinessDocument $documents, UploadedFile $file): User
    {
        if ($user->hasVerifiedBusiness()) {
            throw new AuthorizationException(__('app.profile.business_locked_error'));
        }

        $documents->store($user, $file);

        $user->forceFill(['business_submitted_at' => null])->save();

        return $user->refresh();
    }

    /**
     * Sends the saved business details to an administrator. Name, SSM number and the SSM document are required.
     *
     * @throws AuthorizationException When the business details are already verified.
     * @throws ValidationException When something required is missing.
     */
    public function submitBusiness(User $user): User
    {
        if ($user->hasVerifiedBusiness()) {
            throw new AuthorizationException(__('app.profile.business_locked_error'));
        }

        $missing = [];

        if (blank($user->business_name)) {
            $missing['business_name'] = [__('app.profile.submit_name_required')];
        }

        if (blank($user->ssm_no)) {
            $missing['ssm_no'] = [__('app.profile.submit_ssm_no_required')];
        }

        if ($user->business_document_path === null) {
            $missing['ssm'] = [__('app.profile.submit_document_required')];
        }

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }

        $user->forceFill(['business_submitted_at' => now()])->save();

        $this->activity->record('business.submitted', null, $user, $user);

        return $user->refresh();
    }

    /**
     * An administrator confirms an account's business details. They must have been submitted.
     *
     * @throws AuthorizationException When the actor may not verify this account.
     * @throws ValidationException When the business details have not been submitted.
     */
    public function verifyBusiness(User $actor, User $target): User
    {
        if (! $this->canVerifyBusiness($actor, $target)) {
            throw new AuthorizationException(__('app.profile.verify_forbidden'));
        }

        if ($target->businessStatus() !== 'submitted') {
            throw ValidationException::withMessages([
                'business' => __('app.profile.verify_not_submitted'),
            ]);
        }

        $target->forceFill([
            'business_verified_at' => now(),
            'business_verified_by_user_id' => $actor->id,
        ])->save();

        $this->activity->record('business.verified', null, $actor, $target);

        return $target->refresh();
    }

    /**
     * Any management account may verify another account's business, except its own and a superadmin's.
     */
    public function canVerifyBusiness(User $actor, User $target): bool
    {
        return $actor->isManagement()
            && $actor->id !== $target->id
            && $target->account_type !== AccountType::Superadmin;
    }

    /**
     * Sets the new password and ends every other session of the account, so a stolen session stops working.
     */
    public function changePassword(User $user, string $newPassword, string $currentSessionId): void
    {
        $user->forceFill(['password' => $newPassword])->save();

        $this->activity->record('profile.password_changed', null, $user, $user);

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();
        }
    }

    private function clean(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
