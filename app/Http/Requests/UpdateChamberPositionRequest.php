<?php

namespace App\Http\Requests;

use App\Models\ChamberPosition;
use Illuminate\Validation\Rule;

class UpdateChamberPositionRequest extends StoreChamberPositionRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $position = $this->route('chamberPosition');

        return [
            'name_bm' => [
                'required',
                'string',
                'max:150',
                Rule::unique((new ChamberPosition)->getTable(), 'name_bm')->ignore($position?->id),
            ],
            'name_en' => ['nullable', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
        ];
    }
}
