<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\ChamberPosition;
use App\Models\Member;
use App\Models\PositionAppointment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Records chamber position appointments and keeps the term rules consistent.
 */
class PositionAppointmentService
{
    public function __construct(
        private HtmlSanitizer $sanitizer,
        private EmailNotices $notices,
    ) {}

    /**
     * Create a new appointment for an approved member.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data, User $actor): PositionAppointment
    {
        $this->guard($data);

        $appointment = PositionAppointment::query()->create($this->attributes($data) + [
            'created_by_user_id' => $actor->id,
        ]);

        $member = $appointment->member;

        if ($member !== null) {
            $this->notices->deliver('appointment_made', (string) $member->email, [
                'name' => $member->applicant_name,
                'position' => (string) $appointment->chamberPosition?->name_bm,
                'term_start' => Carbon::parse($appointment->term_start)->translatedFormat('j M Y'),
            ]);
        }

        return $appointment;
    }

    /**
     * Update an appointment, for example to end a term or mark it as no longer current.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(PositionAppointment $appointment, array $data): PositionAppointment
    {
        $this->guard($data);

        if ((int) ($data['chamber_position_id'] ?? 0) !== $appointment->chamber_position_id) {
            $this->ensurePositionIsActive((int) $data['chamber_position_id']);
        }

        $appointment->update($this->attributes($data));

        return $appointment->refresh()->load(['member', 'chamberPosition']);
    }

    /**
     * Reject moves to a deactivated position. Editing an existing appointment on its current position is allowed.
     *
     * @throws ValidationException
     */
    private function ensurePositionIsActive(int $positionId): void
    {
        if (! ChamberPosition::query()->whereKey($positionId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'chamber_position_id' => __('app.validation.position_inactive'),
            ]);
        }
    }

    /**
     * Check member eligibility and term dates before writing.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function guard(array $data): void
    {
        $member = Member::query()->find($data['member_id'] ?? null);

        if ($member === null || $member->status !== MemberStatus::Approved) {
            throw ValidationException::withMessages([
                'member_id' => __('app.validation.member_not_approved'),
            ]);
        }

        $isCurrent = (bool) ($data['is_current'] ?? false);
        $termEnd = $data['term_end'] ?? null;

        if ($isCurrent && $termEnd !== null && Carbon::parse($termEnd)->endOfDay()->isPast()) {
            throw ValidationException::withMessages([
                'term_end' => __('app.validation.term_ended'),
            ]);
        }
    }

    /**
     * Notes are rich text rendered as HTML, so they are sanitized before storage.
     */
    private function sanitizedNotes(?string $notes): ?string
    {
        $clean = $this->sanitizer->sanitize($notes);

        return $clean === '' ? null : $clean;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'member_id' => (int) $data['member_id'],
            'chamber_position_id' => (int) $data['chamber_position_id'],
            'term_start' => $data['term_start'],
            'term_end' => $data['term_end'] ?? null,
            'is_current' => (bool) ($data['is_current'] ?? false),
            'notes' => $this->sanitizedNotes($data['notes'] ?? null),
        ];
    }
}
