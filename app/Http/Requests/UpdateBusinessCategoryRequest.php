<?php

namespace App\Http\Requests;

use App\Models\BusinessCategory;
use Illuminate\Validation\Rule;

class UpdateBusinessCategoryRequest extends StoreBusinessCategoryRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $category = $this->route('businessCategory');

        return [
            'name_bm' => [
                'required',
                'string',
                'max:150',
                Rule::unique((new BusinessCategory)->getTable(), 'name_bm')->ignore($category?->id),
            ],
            'name_en' => ['nullable', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
        ];
    }
}
