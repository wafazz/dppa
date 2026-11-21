<?php

namespace App\Http\Resources;

use App\Models\Member;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Member
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_no' => $this->member_no,
            'applicant_name' => $this->applicant_name,
            'business_name' => $this->business_name,
            'business_category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name_bm' => $this->category->name_bm,
                'name_en' => $this->category->name_en,
            ]),
            'business_category_id' => $this->business_category_id,
            'business_address' => $this->business_address,
            'phone' => $this->phone,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'ssm_no' => $this->ssm_no,
            'business_description' => app(HtmlSanitizer::class)->sanitize($this->business_description),
            'business_needs' => app(HtmlSanitizer::class)->sanitize($this->business_needs),
            'account' => $this->whenLoaded('account', fn () => $this->account === null ? null : [
                'id' => $this->account->id,
                'email' => $this->account->email,
                'is_active' => (bool) $this->account->is_active,
                'has_password' => $this->account->password !== null,
                'business_status' => $this->account->businessStatus(),
                'has_business_document' => $this->account->business_document_path !== null,
            ]),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'applied_at' => $this->applied_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'consent_given_at' => $this->consent_given_at?->toIso8601String(),
            'consent_version' => $this->consent_version,
            'status_logs' => MemberStatusLogResource::collection($this->whenLoaded('statusLogs')),
            'appointments' => PositionAppointmentResource::collection($this->whenLoaded('appointments')),
        ];
    }
}
