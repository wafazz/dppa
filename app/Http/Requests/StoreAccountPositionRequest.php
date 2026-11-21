<?php

namespace App\Http\Requests;

use App\Models\AccountPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountPositionRequest extends FormRequest
{
    /**
     * Route middleware restricts this request to the superadmin.
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
            'name_bm' => ['required', 'string', 'max:150', Rule::unique((new AccountPosition)->getTable(), 'name_bm')],
            'name_en' => ['nullable', 'string', 'max:150'],
            'level' => ['required', 'integer', 'between:1,999', Rule::unique((new AccountPosition)->getTable(), 'level')],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
