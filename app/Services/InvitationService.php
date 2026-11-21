<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\Member;
use App\Models\User;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates registration invitations and redeems them atomically so a link cannot be over-used.
 */
class InvitationService
{
    /**
     * @param  array{note?: ?string, recipient_email?: ?string, expires_in_days: int, max_uses: int}  $data
     */
    public function create(User $actor, array $data): Invitation
    {
        $recipient = isset($data['recipient_email']) && trim((string) $data['recipient_email']) !== ''
            ? strtolower(trim((string) $data['recipient_email']))
            : null;

        return Invitation::query()->create([
            'token' => bin2hex(random_bytes(32)),
            'created_by_user_id' => $actor->id,
            'note' => isset($data['note']) && trim((string) $data['note']) !== '' ? trim((string) $data['note']) : null,
            'recipient_email' => $recipient,
            'expires_at' => Carbon::now()->addDays($data['expires_in_days']),
            'max_uses' => $data['max_uses'],
            'used_count' => 0,
        ]);
    }

    public function revoke(Invitation $invitation): Invitation
    {
        if ($invitation->revoked_at === null) {
            $invitation->update(['revoked_at' => now()]);
        }

        return $invitation->refresh();
    }

    /**
     * Lock the invitation, re-check that it is still usable, count the use, and create the member in one transaction.
     *
     * @param  Closure(Invitation): Member  $createMember
     *
     * @throws ValidationException When the link is unknown, expired, revoked or used up.
     */
    public function redeem(string $token, Closure $createMember): Member
    {
        return DB::transaction(function () use ($token, $createMember): Member {
            $invitation = Invitation::query()->where('token', $token)->lockForUpdate()->first();

            if ($invitation === null || ! $invitation->isUsable()) {
                throw ValidationException::withMessages([
                    'token' => 'Pautan jemputan tidak sah atau telah tamat tempoh.',
                ]);
            }

            $member = $createMember($invitation);

            $invitation->increment('used_count');

            return $member;
        });
    }
}
