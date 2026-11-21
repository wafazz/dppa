<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invitation
 */
class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => route('registration.invited', ['token' => $this->token]),
            'note' => $this->note,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'status' => $this->statusCode(),
            'status_label' => $this->statusLabel(),
            'is_usable' => $this->isUsable(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function statusCode(): string
    {
        return match (true) {
            $this->revoked_at !== null => 'revoked',
            $this->used_count >= $this->max_uses => 'used_up',
            $this->expires_at->isPast() => 'expired',
            default => 'active',
        };
    }

    private function statusLabel(): string
    {
        return __('app.enums.invitation_status.'.$this->statusCode());
    }
}
