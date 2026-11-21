<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\Invitation;
use App\Models\Member;

/**
 * Creates and updates member records, keeping rich-text fields sanitized and searchable.
 */
class MemberApplicationService
{
    public function __construct(private HtmlSanitizer $sanitizer) {}

    /**
     * Store a new public membership application with status Pending.
     *
     * @param  array<string, mixed>  $data  Validated registration input.
     */
    public function submit(array $data, ?Invitation $invitation = null): Member
    {
        return Member::query()->create($this->attributes($data) + [
            'invitation_id' => $invitation?->id,
            'status' => MemberStatus::Pending,
            'applied_at' => now(),
            'consent_given_at' => now(),
            'consent_version' => config('membership.consent_version'),
            // Optional and separate from the consent above: announcements need their own agreement.
            'announcement_consent_at' => ! empty($data['announcement_consent']) ? now() : null,
        ]);
    }

    /**
     * Update the details of an existing member. Status is changed separately by MemberStatusService.
     *
     * @param  array<string, mixed>  $data  Validated member input.
     */
    public function updateDetails(Member $member, array $data): Member
    {
        $attributes = $this->attributes($data);

        // A changed address has to be confirmed again.
        if ($attributes['email'] !== $member->email) {
            $attributes['email_verified_at'] = null;
        }

        $member->forceFill($attributes)->save();

        return $member->refresh();
    }

    /**
     * Normalise validated input into the columns stored on the member record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $description = $this->sanitizer->sanitize($data['business_description'] ?? null);
        $needs = $this->sanitizer->sanitize($data['business_needs'] ?? null);

        return [
            'applicant_name' => trim((string) $data['applicant_name']),
            'business_name' => trim((string) $data['business_name']),
            'business_category_id' => (int) $data['business_category_id'],
            'business_address' => trim((string) $data['business_address']),
            'phone' => trim((string) $data['phone']),
            'email' => strtolower(trim((string) $data['email'])),
            'ssm_no' => $this->normalizeSsm($data['ssm_no'] ?? null),
            'business_description' => $description,
            'business_needs' => $needs,
            'search_text' => trim($this->sanitizer->toPlainText($description).' '.$this->sanitizer->toPlainText($needs)),
        ];
    }

    /**
     * Store SSM numbers in one consistent form so duplicate checks match reliably.
     */
    private function normalizeSsm(?string $ssmNo): ?string
    {
        $value = strtoupper(trim((string) $ssmNo));

        return $value === '' ? null : $value;
    }
}
