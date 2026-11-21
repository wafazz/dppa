<?php

namespace App\Http\Requests;

use App\Models\ChamberPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChamberPositionRequest extends FormRequest
{
    /**
     * Route middleware restricts position changes to administrators.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name_bm' => ['required', 'string', 'max:150', Rule::unique((new ChamberPosition)->getTable(), 'name_bm')],
            'name_en' => ['nullable', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
        ];
    }
}
