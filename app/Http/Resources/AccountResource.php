<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\AccountService;
use App\Services\ProfileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'account_type' => $this->account_type->value,
            'account_type_label' => $this->account_type->label(),
            'role' => $this->isStaff() ? $this->role->value : null,
            'role_label' => $this->isStaff() ? $this->role->label() : null,
            'can_invite' => (bool) $this->can_invite,
            'is_active' => (bool) $this->is_active,
            'account_position' => $this->whenLoaded('accountPosition', fn () => $this->accountPosition === null ? null : [
                'id' => $this->accountPosition->id,
                'name_bm' => $this->accountPosition->name_bm,
                'level' => $this->accountPosition->level,
            ]),
            'business_name' => $this->business_name,
            'business_verified' => $this->hasVerifiedBusiness(),
            'business_status' => $this->businessStatus(),
            'has_business_document' => $this->business_document_path !== null,
            'business_document_name' => $this->business_document_name,
            'can_verify_business' => $this->businessStatus() === 'submitted'
                && app(ProfileService::class)->canVerifyBusiness($request->user(), $this->resource),
            'can_manage' => app(AccountService::class)->canManage($request->user(), $this->resource),
        ];
    }
}
