<?php

namespace App\Http\Resources;

use App\Models\AccountPosition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountPosition
 */
class AccountPositionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_bm' => $this->name_bm,
            'name_en' => $this->name_en,
            'level' => $this->level,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
