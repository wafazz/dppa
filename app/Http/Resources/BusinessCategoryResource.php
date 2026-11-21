<?php

namespace App\Http\Resources;

use App\Models\BusinessCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessCategory
 */
class BusinessCategoryResource extends JsonResource
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
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
