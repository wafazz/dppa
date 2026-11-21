<?php

namespace App\Http\Resources;

use App\Models\PositionAppointment;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PositionAppointment
 */
class PositionAppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'member' => $this->whenLoaded('member', fn () => [
                'id' => $this->member->id,
                'member_no' => $this->member->member_no,
                'applicant_name' => $this->member->applicant_name,
                'business_name' => $this->member->business_name,
            ]),
            'chamber_position_id' => $this->chamber_position_id,
            'chamber_position' => $this->whenLoaded('chamberPosition', fn () => [
                'id' => $this->chamberPosition->id,
                'name_bm' => $this->chamberPosition->name_bm,
                'name_en' => $this->chamberPosition->name_en,
            ]),
            'term_start' => $this->term_start?->toDateString(),
            'term_end' => $this->term_end?->toDateString(),
            'is_current' => $this->is_current,
            'notes' => $this->notes === null ? null : app(HtmlSanitizer::class)->sanitize($this->notes),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
