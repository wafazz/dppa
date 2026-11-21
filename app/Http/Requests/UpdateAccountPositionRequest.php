<?php

namespace App\Http\Requests;

use App\Models\AccountPosition;
use Illuminate\Validation\Rule;

class UpdateAccountPositionRequest extends StoreAccountPositionRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $position = $this->route('accountPosition');
        $table = (new AccountPosition)->getTable();

        return [
            'name_bm' => ['required', 'string', 'max:150', Rule::unique($table, 'name_bm')->ignore($position?->id)],
            'name_en' => ['nullable', 'string', 'max:150'],
            'level' => ['required', 'integer', 'between:1,999', Rule::unique($table, 'level')->ignore($position?->id)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
